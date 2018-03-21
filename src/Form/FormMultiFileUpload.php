<?php

/*
 * Copyright (c) 2018 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\MultiFileUploadBundle\Form;

use Contao\CoreBundle\Command\SymlinksCommand;
use Contao\Database;
use Contao\DataContainer;
use Contao\Dbafs;
use Contao\File;
use Contao\FilesModel;
use Contao\Folder;
use Contao\StringUtil;
use Contao\System;
use Contao\Upload;
use Contao\Validator;
use HeimrichHannot\AjaxBundle\Response\ResponseData;
use HeimrichHannot\AjaxBundle\Response\ResponseError;
use HeimrichHannot\AjaxBundle\Response\ResponseSuccess;
use HeimrichHannot\MultiFileUploadBundle\Backend\MultiFileUpload;
use Model\Collection;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class FormMultiFileUpload extends Upload
{
    const UNIQID_PREFIX = 'mfuid';

    /**
     * Submit user input.
     *
     * @var bool
     */
    protected $submitInput = true;

    /**
     * @var string
     */
    protected $uploadAction = 'upload';

    /**
     * For binary(16) fields the values must be provided as single field.
     *
     * @var bool
     */
    protected $singleFile = false;

    public function __construct($attributes = null)
    {
        // this is the case for 'onsubmit_callback' => 'multifileupload_moveFiles'
        if (null === $attributes) {
            $attributes = [];
            $attributes['isSubmitCallback'] = true;
        }

        // check against arrAttributes, as 'onsubmit_callback' => 'multifileupload_moveFiles' does not provide valid attributes
        if (!isset($attributes['isSubmitCallback']) && !isset($attributes['uploadFolder'])) {
            throw new \Exception(sprintf($GLOBALS['TL_LANG']['ERR']['noUploadFolderDeclared'], $this->name));
        }

        if (isset($attributes['strTable'])) {
            $this->isSingleFile($attributes);
        }

        $attributes['uploadAction'] = $this->uploadAction;

        if (System::getContainer()->get('huh.utils.container')->isFrontend()) {
            $attributes['uploadActionParams'] = http_build_query(System::getContainer()->get('huh.ajax.action')->getParams(MultiFileUpload::NAME, $this->uploadAction));
        }

        $attributes['parallelUploads'] = 1; // in order to provide new token for each ajax request, upload one by one

        $attributes['addRemoveLinks'] = isset($attributes['addRemoveLinks']) ? $attributes['addRemoveLinks'] : true;

        if (isset($attributes['value']) && !is_array($attributes['value']) && !Validator::isBinaryUuid($attributes['value'])) {
            $attributes['value'] = json_decode($attributes['value']);
        }

        // bin to string -> never pass binary to the widget!!
        if (isset($attributes['value'])) {
            if (is_array($attributes['value'])) {
                $attributes['value'] = array_map(function ($val) {
                    return Validator::isBinaryUuid($val) ? StringUtil::binToUuid($val) : $val;
                }, $attributes['value']);
            } else {
                $attributes['value'] = [
                    Validator::isBinaryUuid($attributes['value']) ? StringUtil::binToUuid($attributes['value']) : $attributes['value'],
                ];
            }
        }

        parent::__construct($attributes);

        $this->objUploader = new MultiFileUpload($attributes, $this);

        $this->setAttributes($attributes);

        if ($this->strTable) {
            // add onsubmit_callback at first onsubmit_callback position: move files after form submission
            if (is_array($GLOBALS['TL_DCA'][$this->strTable]['config']['onsubmit_callback'])) {
                $GLOBALS['TL_DCA'][$this->strTable]['config']['onsubmit_callback'] = ['multifileupload_moveFiles' => ['HeimrichHannot\MultiFileUploadBundle\Form\FormMultiFileUpload', 'moveFiles']] + $GLOBALS['TL_DCA'][$this->strTable]['config']['onsubmit_callback'];
            } else {
                $GLOBALS['TL_DCA'][$this->strTable]['config']['onsubmit_callback'] = ['multifileupload_moveFiles' => ['HeimrichHannot\MultiFileUploadBundle\Form\FormMultiFileUpload', 'moveFiles']];
            }
        }

        System::getContainer()->get('huh.ajax')->runActiveAction(MultiFileUpload::NAME, MultiFileUpload::ACTION_UPLOAD, $this);
    }

    /**
     * @param DataContainer $dc
     *
     * @throws \Exception
     */
    public function moveFiles(DataContainer $dc)
    {
        $arrPost = System::getContainer()->get('huh.request')->getAllPost();

        foreach ($arrPost as $key => $value) {
            if (!isset($GLOBALS['TL_DCA'][$dc->table]['fields'][$key])) {
                continue;
            }

            $arrData = $GLOBALS['TL_DCA'][$dc->table]['fields'][$key];

            if (MultiFileUpload::NAME !== $arrData['inputType']) {
                continue;
            }

            $arrFiles = deserialize($dc->activeRecord->{$key});

            $strUploadFolder = System::getContainer()->get('huh.utils.file')->getFolderFromDca($arrData['eval']['uploadFolder'], $dc);

            if (null === $strUploadFolder) {
                throw new \Exception(sprintf($GLOBALS['TL_LANG']['ERR']['uploadNoUploadFolderDeclared'], $key, System::getContainer()->getParameter('huh.multifileupload.uploadtmp')));
            }

            if (!is_array($arrFiles)) {
                $arrFiles = [$arrFiles];
            }

            /** @var Collection|FilesModel $filesModel */
            $filesModel = System::getContainer()->get('contao.framework')->getAdapter(FilesModel::class)->findMultipleByUuids($arrFiles);

            if (null === $filesModel) {
                continue;
            }

            $arrPaths = $filesModel->fetchEach('path');
            $arrTargets = [];

            // do not loop over $objFileModels as $objFile->close() will pull models away
            foreach ($arrPaths as $strPath) {
                $file = new File($strPath);
                $target = $strUploadFolder.'/'.$file->name;

                // upload_path_callback
                if (isset($arrData['upload_path_callback']) && is_array($arrData['upload_path_callback'])) {
                    foreach ($arrData['upload_path_callback'] as $callback) {
                        $target = System::importStatic($callback[0])->{$callback[1]}($target, $file, $dc) ?: $target;
                    }
                }

                if (System::getContainer()->get('huh.utils.string')->startsWith($file->path, ltrim($target, '/'))) {
                    continue;
                }

                $target = System::getContainer()->get('huh.utils.file')->getUniqueFileNameWithinTarget($target, static::UNIQID_PREFIX);

                if ($file->renameTo($target)) {
                    $arrTargets[] = $target;
                    $objModel = $file->getModel();

                    // Update the database
                    if (null === $objModel && System::getContainer()->get('contao.framework')->getAdapter(Dbafs::class)->shouldBeSynchronized($target)) {
                        $objModel = System::getContainer()->get('contao.framework')->getAdapter(Dbafs::class)->addResource($target);
                    }

                    continue;
                }

                $arrTargets[] = $strPath;
            }

            // HOOK: post upload callback
            if (isset($GLOBALS['TL_HOOKS']['postUpload']) && is_array($GLOBALS['TL_HOOKS']['postUpload'])) {
                foreach ($GLOBALS['TL_HOOKS']['postUpload'] as $callback) {
                    if (is_array($callback)) {
                        System::importStatic($callback[0])->{$callback[1]}($arrTargets);
                    } elseif (is_callable($callback)) {
                        $callback($arrTargets);
                    }
                }
            }
        }
    }

    public function upload()
    {
        $arrUuids = [];
        $varReturn = null;
        // check for the request token
        if (!System::getContainer()->get('huh.request')->hasPost('requestToken') || !\RequestToken::validate(System::getContainer()->get('huh.request')->getPost('requestToken'))) {
            $objResponse = new ResponseError();
            $objResponse->setMessage('Invalid Request Token!');
            $objResponse->output();
        }

        if (!System::getContainer()->get('huh.request')->files->has($this->name)) {
            return;
        }

        $objTmpFolder = new Folder(System::getContainer()->getParameter('huh.multifileupload.uploadtmp'));

        // tmp directory is not public, mandatory for preview images
        if (!file_exists(System::getContainer()->getParameter('contao.web_dir').DIRECTORY_SEPARATOR.System::getContainer()->getParameter('huh.multifileupload.uploadtmp'))) {
            $objTmpFolder->unprotect();
            $command = new SymlinksCommand();
            $command->setContainer(System::getContainer());
            $input = new ArrayInput([]);
            $output = new NullOutput();
            $command->run($input, $output);
        }

        $strField = $this->name;
        $varFile = System::getContainer()->get('huh.request')->files->get($strField);
        // Multi-files upload at once
        if (is_array($varFile)) {
            // prevent disk flooding
            if (count($varFile) > $this->maxFiles) {
                $objResponse = new ResponseError();
                $objResponse->setMessage('Bulk file upload violation.');
                $objResponse->output();
            }

            /*
             * @var UploadedFile
             */
            foreach ($varFile as $strKey => $objFile) {
                $arrFile = $this->uploadFile($objFile, $objTmpFolder->path);
                $varReturn[] = $arrFile;

                if (isset($varReturn['uuid']) && Validator::isUuid($arrFile['uuid'])) {
                    $arrUuids[] = $arrFile['uuid'];
                }
            }
        } else {
            // Single-file upload
            /**
             * @var UploadedFile
             */
            $varReturn = $this->uploadFile($varFile, $objTmpFolder->path);

            if (isset($varReturn['uuid']) && Validator::isUuid($varReturn['uuid'])) {
                $arrUuids[] = $varReturn['uuid'];
            }
        }

        if (null !== $varReturn) {
            $this->varValue = $arrUuids;
            $objResponse = new ResponseSuccess();
            $objResult = new ResponseData();
            $objResult->setData($varReturn);
            $objResponse->setResult($objResult);

            return $objResponse;
        }
    }

    public function generateLabel()
    {
        if ('' === $this->strLabel || null === $this->strLabel) {
            return '';
        }

        return sprintf('<label%s%s>%s%s%s</label>', ($this->blnForAttribute ? ' for="ctrl_'.$this->strId.'"' : ''), (('' !== $this->strClass) ? ' class="'.$this->strClass.'"' : ''), ($this->mandatory ? '<span class="invisible">'.$GLOBALS['TL_LANG']['MSC']['mandatory'].' </span>' : ''), $this->strLabel, ($this->mandatory ? '<span class="mandatory">*</span>' : ''));
    }

    /**
     * @param mixed $input
     *
     * @return array|bool|mixed|string
     */
    public function validator($input)
    {
        if ('' === $input || '[]' === $input) {
            $input = '[]';
        }

        $arrFiles = json_decode($input);
        $arrDeleted = json_decode(($this->getPost('deleted_'.$this->strName)));
        $blnEmpty = false;

        if (is_array($arrFiles) && is_array($arrDeleted)) {
            $blnEmpty = empty(array_diff($arrFiles, $arrDeleted));
        }
        if ($this->mandatory && $blnEmpty) {
            if ('' === $this->strLabel || null === $this->strLabel) {
                $this->addError($GLOBALS['TL_LANG']['ERR']['mdtryNoLabel']);
            } else {
                $this->addError(sprintf($GLOBALS['TL_LANG']['ERR']['mandatory'], $this->strLabel));
            }

            // do no delete last file if mandatory
            return false;
        }

        if (!$this->skipDeleteAfterSubmit && is_array($arrDeleted)) {
            $this->deleteScheduledFiles($arrDeleted);
        }

        if (is_array($arrFiles)) {
            foreach ($arrFiles as $k => $v) {
                if (!Validator::isUuid($v)) {
                    $this->addError($GLOBALS['TL_LANG']['ERR']['invalidUuid']);

                    return false;
                }

                // cleanup non existing files on save
                if (null === ($file = System::getContainer()->get('huh.utils.file')->getFileFromUuid($v)) || !$file->exists()) {
                    unset($arrFiles[$k]);
                    continue;
                }

                $arrFiles[$k] = StringUtil::uuidToBin($v);
            }
        } else {
            if (!Validator::isUuid($arrFiles)) {
                $this->addError($GLOBALS['TL_LANG']['ERR']['invalidUuid']);

                return false;
            }

            // cleanup non existing files on save
            if (null === ($file = System::getContainer()->get('huh.utils.file')->getFileFromUuid($arrFiles)) || !$file->exists()) {
                return false;
            }

            $arrFiles = StringUtil::uuidToBin($arrFiles);
        }

        return $this->singleFile ? reset($arrFiles) : $arrFiles;
    }

    /**
     * @return MultiFileUpload
     */
    public function getUploader()
    {
        return $this->objUploader;
    }

    /**
     * @param array $scheduledFiles
     *
     * @return array
     */
    public function deleteScheduledFiles(array $scheduledFiles)
    {
        $arrFiles = [];

        if (empty($scheduledFiles)) {
            return $arrFiles;
        }

        foreach ($scheduledFiles as $strUuid) {
            if (null !== ($objFile = System::getContainer()->get('huh.utils.file')->getFileFromUuid($strUuid)) && $objFile->exists()) {
                if (true === $objFile->delete()) {
                    $arrFiles[] = $strUuid;
                }
            }
        }
    }

    /**
     * @param array $attributes
     */
    protected function isSingleFile(array $attributes)
    {
        // no database field (e.g. multi_column_editor)
        if (!System::getContainer()->get('contao.framework')->createInstance(Database::class)->fieldExists($attributes['name'], $attributes['strTable']) && 'radio' === $attributes['fieldType']) {
            $this->singleFile = true;
        } else {
            // field exists, check database field type
            $arrTableFields = System::getContainer()->get('contao.framework')->createInstance(Database::class)->listFields($attributes['strTable']);

            foreach ($arrTableFields as $arrField) {
                if ($arrField['name'] === $attributes['name'] && 'index' !== $arrField['type'] && 'binary' === $arrField['type']) {
                    $this->singleFile = true;
                    break;
                }
            }
        }
    }

    /**
     * @param array $attributes
     */
    protected function setAttributes(array $attributes)
    {
        if (null !== $this->objUploader && is_array($this->objUploader->getData())) {
            $attributes = array_merge($attributes, $this->objUploader->getData());
        }

        foreach ($attributes as $strKey => $varValue) {
            $this->{$strKey} = $varValue;
        }
    }

    /**
     * Validate a given extension.
     *
     * @param UploadedFile $objUploadFile The uploaded file object
     *
     * @return string|bool The error message or false for no error
     */
    protected function validateExtension(UploadedFile $objUploadFile)
    {
        $error = false;

        $strAllowed = $this->extensions ?: \Config::get('uploadTypes');

        $arrAllowed = StringUtil::trimsplit(',', $strAllowed);

        $strExtension = $objUploadFile->getClientOriginalExtension();

        if (!$strExtension || !is_array($arrAllowed) || !in_array($strExtension, $arrAllowed, true)) {
            return sprintf(sprintf($GLOBALS['TL_LANG']['ERR']['illegalFileExtension'], $strExtension));
        }

        // compare client mime type with mime type check result from server (e.g. user uploaded a php file with jpg extension)
        if (!$this->validateMimeType($objUploadFile->getClientMimeType(), $objUploadFile->getMimeType())) {
            return sprintf(sprintf($GLOBALS['TL_LANG']['ERR']['illegalMimeType'], $objUploadFile->getMimeType()));
        }

        return $error;
    }

    /**
     * @param $mimeClient
     * @param $mimeDetected
     *
     * @return bool
     */
    protected function validateMimeType($mimeClient, $mimeDetected)
    {
        if ($mimeClient !== $mimeDetected) {
            // allow safe mime types
            switch ($mimeDetected) {
                // swf might be detected as `application/x-shockwave-flash` instead of `application/vnd.adobe.flash.movie`
                case 'application/x-shockwave-flash':
                    // css files might be detected as the following instead of 'text/css'
                case 'text/x-asm':
                    // csv files might be detected as the following instead of 'text/csv'
                case 'text/plain':
                case 'text/csv':
                case 'text/x-csv':
                case 'text/comma-separated-values':
                case 'text/x-comma-separated-values':
                case 'text/tab-separated-values':
                    return true;
                    break;
            }

            return false;
        }

        return true;
    }

    /**
     * Validate the uploaded file.
     *
     * @param File $objFile
     *
     * @return string|bool The error message or false for no error
     */
    protected function validateUpload(File $objFile)
    {
        if ($objFile->isImage) {
            $minWidth = System::getContainer()->get('huh.utils.image')->getPixelValue($this->minImageWidth);
            $minHeight = System::getContainer()->get('huh.utils.image')->getPixelValue($this->minImageHeight);

            $maxWidth = System::getContainer()->get('huh.utils.image')->getPixelValue($this->maxImageWidth);
            $maxHeight = System::getContainer()->get('huh.utils.image')->getPixelValue($this->maxImageHeight);

            if ($minWidth > 0 && $objFile->width < $minWidth) {
                return sprintf($this->minImageWidthErrorText ?: $GLOBALS['TL_LANG']['ERR']['minWidth'], $minWidth, $objFile->width);
            }

            if ($minHeight > 0 && $objFile->height < $minHeight) {
                return sprintf($this->minImageHeightErrorText ?: $GLOBALS['TL_LANG']['ERR']['minHeight'], $minHeight, $objFile->height);
            }

            if ($maxWidth > 0 && $objFile->width > $maxWidth) {
                return sprintf($this->maxImageWidthErrorText ?: $GLOBALS['TL_LANG']['ERR']['maxWidth'], $maxWidth, $objFile->width);
            }

            if ($maxHeight > 0 && $objFile->height > $maxHeight) {
                return sprintf($this->maxImageHeightErrorText ?: $GLOBALS['TL_LANG']['ERR']['maxHeight'], $maxHeight, $objFile->height);
            }
        }

        return false;
    }

    /**
     * Upload a file, store to $uploadFile and create database entry.
     *
     * @param UploadedFile $uploadFile   UploadedFile        The uploaded file object
     * @param string       $uploadFolder The upload target folder within contao files folder
     *
     * @return array|bool Returns array with file information on success. Returns false if no valid file, file cannot be moved or destination lies outside the
     *                    contao upload directory.
     */
    protected function uploadFile(UploadedFile $uploadFile, string $uploadFolder)
    {
        $strOriginalFileName = rawurldecode($uploadFile->getClientOriginalName()); // e.g. double quotes are escaped with %22 -> decode it
        $strOriginalFileNameEncoded = rawurlencode($strOriginalFileName);
        $strSanitizedFileName = System::getContainer()->get('huh.utils.file')->sanitizeFileName($uploadFile->getClientOriginalName());

        if ($uploadFile->getError()) {
            return [
                'error' => $uploadFile->getError(),
                'filenameOrigEncoded' => $strOriginalFileNameEncoded,
                'filenameSanitized' => $strSanitizedFileName,
            ];
        }

        $error = false;

        $strTargetFileName = System::getContainer()->get('huh.utils.file')->addUniqueIdToFilename($strSanitizedFileName, static::UNIQID_PREFIX);

        if (false !== ($error = $this->validateExtension($uploadFile))) {
            return [
                'error' => $error,
                'filenameOrigEncoded' => $strOriginalFileNameEncoded,
                'filenameSanitized' => $strSanitizedFileName,
            ];
        }

        try {
            $uploadFile = $uploadFile->move(TL_ROOT.'/'.$uploadFolder, $strTargetFileName);
        } catch (FileException $e) {
            return [
                'error' => sprintf($GLOBALS['TL_LANG']['ERR']['moveUploadFile'], $e->getMessage()),
                'filenameOrigEncoded' => $strOriginalFileNameEncoded,
                'filenameSanitized' => $strSanitizedFileName,
            ];
        }

        $arrData = [
            'filename' => $strTargetFileName,
            'filenameOrigEncoded' => $strOriginalFileNameEncoded,
            'filenameSanitized' => $strSanitizedFileName,
        ];

        $strRelativePath = ltrim(str_replace(TL_ROOT, '', $uploadFile->getRealPath()), DIRECTORY_SEPARATOR);

        $objFile = null;
        $objModel = null;

        try {
            // add db record
            $objFile = System::getContainer()->get('contao.framework')->createInstance(File::class, [$strRelativePath]);
            $objModel = $objFile->getModel();

            // Update the database
            if (null === $objModel && System::getContainer()->get('contao.framework')->getAdapter(Dbafs::class)->shouldBeSynchronized($strRelativePath)) {
                $objModel = System::getContainer()->get('contao.framework')->getAdapter(Dbafs::class)->addResource($strRelativePath);
            }

            $strUuid = $objModel->uuid;
        } catch (\InvalidArgumentException $e) {
            // remove file from file system
            @unlink(TL_ROOT.'/'.$strRelativePath);

            return [
                'error' => $GLOBALS['TL_LANG']['ERR']['outsideUploadDirectory'],
                'filenameOrigEncoded' => $strOriginalFileNameEncoded,
                'filenameSanitized' => $strSanitizedFileName,
            ];
        }

        if (!$objFile instanceof File || null === $objModel) {
            // remove file from file system
            @unlink(TL_ROOT.'/'.$strRelativePath);

            return [
                'error' => $GLOBALS['TL_LANG']['ERR']['outsideUploadDirectory'],
                'filenameOrigEncoded' => $strOriginalFileNameEncoded,
                'filenameSanitized' => $strSanitizedFileName,
            ];
        }

        if (false !== ($error = $this->validateUpload($objFile))) {
            return [
                'error' => $error,
                'filenameOrigEncoded' => $strOriginalFileNameEncoded,
                'filenameSanitized' => $strSanitizedFileName,
            ];
        }

        // upload_path_callback
        if (is_array($this->validate_upload_callback)) {
            foreach ($this->validate_upload_callback as $callback) {
                if (!class_exists($callback[0])) {
                    continue;
                }

                $objCallback = System::importStatic($callback[0]);

                if (!method_exists($objCallback, $callback[1])) {
                    continue;
                }

                if ($errorCallback = $objCallback->{$callback[1]}($objFile, $this)) {
                    $error = $errorCallback;
                    break; // stop validation on first error
                }
            }
        }

        if (false === $error && false !== ($arrInfo = $this->objUploader->prepareFile($strUuid))) {
            $arrData = array_merge($arrData, $arrInfo);

            return $arrData;
        }

        $arrData['error'] = $error;

        // remove invalid files from tmp folder
        if ($objFile instanceof File) {
            $objFile->delete();
        }

        return $arrData;
    }
}
