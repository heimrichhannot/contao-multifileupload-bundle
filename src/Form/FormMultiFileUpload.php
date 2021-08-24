<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\MultiFileUploadBundle\Form;

use Contao\Config;
use Contao\Database;
use Contao\DataContainer;
use Contao\Dbafs;
use Contao\File;
use Contao\FilesModel;
use Contao\Folder;
use Contao\Input;
use Contao\RequestToken;
use Contao\StringUtil;
use Contao\System;
use Contao\Upload;
use Contao\Validator;
use HeimrichHannot\AjaxBundle\Response\ResponseData;
use HeimrichHannot\AjaxBundle\Response\ResponseSuccess;
use HeimrichHannot\MultiFileUploadBundle\Asset\FrontendAsset;
use HeimrichHannot\MultiFileUploadBundle\Backend\MultiFileUpload;
use HeimrichHannot\MultiFileUploadBundle\Exception\InvalidImageException;
use HeimrichHannot\MultiFileUploadBundle\Response\DropzoneErrorResponse;
use Model\Collection;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class FormMultiFileUpload extends Upload
{
    const UNIQID_PREFIX = 'mfuid';

    protected $strTemplate = 'form_multifileupload';

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
    protected $container;

    public function __construct($attributes = null)
    {
        $this->container = System::getContainer();

        // this is the case for 'onsubmit_callback' => 'multifileupload_moveFiles'
        if (null === $attributes) {
            $attributes = [];
            $attributes['isSubmitCallback'] = true;
        }

        // check against arrAttributes, as 'onsubmit_callback' => 'multifileupload_moveFiles' does not provide valid attributes
        if (!isset($attributes['isSubmitCallback']) && !isset($attributes['uploadFolder'])) {
            throw new \Exception(sprintf($GLOBALS['TL_LANG']['ERR']['noUploadFolderDeclared'], $this->name));
        }

        $attributes = $this->setAttributes($attributes);

        parent::__construct($attributes);

        // form generator fix, here the id of the field is
        if (is_numeric($attributes['id'])) {
            $attributes['id'] = $attributes['name'];
        }

        $this->objUploader = new MultiFileUpload($attributes, $this);

        $this->container->get(FrontendAsset::class)->addFrontendAssets();

        $this->setVariables($attributes);

        if ($this->strTable) {
            // add onsubmit_callback at first onsubmit_callback position: move files after form submission
            if (\is_array($GLOBALS['TL_DCA'][$this->strTable]['config']['onsubmit_callback'])) {
                $GLOBALS['TL_DCA'][$this->strTable]['config']['onsubmit_callback'] = ['multifileupload_moveFiles' => ['HeimrichHannot\MultiFileUploadBundle\Form\FormMultiFileUpload', 'moveFiles']] + $GLOBALS['TL_DCA'][$this->strTable]['config']['onsubmit_callback'];
            } else {
                $GLOBALS['TL_DCA'][$this->strTable]['config']['onsubmit_callback'] = ['multifileupload_moveFiles' => ['HeimrichHannot\MultiFileUploadBundle\Form\FormMultiFileUpload', 'moveFiles']];
            }
        }

        $this->container->get('huh.ajax')->runActiveAction(MultiFileUpload::NAME, MultiFileUpload::ACTION_UPLOAD, $this);
    }

    /**
     * @throws \Exception
     */
    public function moveFiles(DataContainer $dc)
    {
        $container = System::getContainer();

        $arrPost = $this->container->get('huh.request')->getAllPost();

        foreach ($arrPost as $key => $value) {
            if (!isset($GLOBALS['TL_DCA'][$dc->table]['fields'][$key])) {
                continue;
            }

            $arrData = $GLOBALS['TL_DCA'][$dc->table]['fields'][$key];

            if (MultiFileUpload::NAME !== $arrData['inputType']) {
                continue;
            }

            $arrFiles = StringUtil::deserialize($dc->activeRecord->{$key});

            $strUploadFolder = $this->container->get('huh.utils.file')->getFolderFromDca($arrData['eval']['uploadFolder'], $dc);

            if (null === $strUploadFolder) {
                throw new \Exception(sprintf($GLOBALS['TL_LANG']['ERR']['uploadNoUploadFolderDeclared'], $key, $container->getParameter('huh.multifileupload.upload_tmp')));
            }

            if (!\is_array($arrFiles)) {
                $arrFiles = [$arrFiles];
            }

            /** @var Collection|FilesModel $filesModel */
            $filesModel = $container->get('contao.framework')->getAdapter(FilesModel::class)->findMultipleByUuids($arrFiles);

            if (null === $filesModel) {
                continue;
            }

            $arrPaths = $filesModel->fetchEach('path');
            $arrTargets = [];

            // do not loop over $objFileModels as $objFile->close() will pull models away
            foreach ($arrPaths as $strPath) {
                $file = new File($strPath);
                $target = $strUploadFolder.'/'.$file->name;

                // uploadPathCallback
                if (isset($arrData['uploadPathCallback']) && \is_array($arrData['uploadPathCallback'])) {
                    foreach ($arrData['uploadPathCallback'] as $callback) {
                        $target = System::importStatic($callback[0])->{$callback[1]}($target, $file, $dc) ?: $target;
                    }
                }

                if ($container->get('huh.utils.string')->startsWith($file->path, ltrim($target, '/'))) {
                    continue;
                }

                $target = $container->get('huh.utils.file')->getUniqueFileNameWithinTarget($target, static::UNIQID_PREFIX);
                // delete file from database if file with same path do not exists
                $databaseUtil = System::getContainer()->get('huh.utils.database');

                // delete file from database if file with same path do not exists
                if (null !== ($storedFiles = $databaseUtil->findResultsBy('tl_files', ['tl_files.path=?'], [$target]))) {
                    foreach ($storedFiles->fetchAllAssoc() as $storedFile) {
                        if (file_exists($storedFile['path'])) {
                            continue;
                        }
                        $databaseUtil->delete('tl_files', 'tl_files.id=?', [$storedFile['id']]);
                    }
                }

                if ($file->renameTo($target)) {
                    $arrTargets[] = $target;
                    $objModel = $file->getModel();

                    // Update the database
                    if (null === $objModel && $container->get('contao.framework')->getAdapter(Dbafs::class)->shouldBeSynchronized($target)) {
                        $objModel = $container->get('contao.framework')->getAdapter(Dbafs::class)->addResource($target);
                    }

                    continue;
                }

                $arrTargets[] = $strPath;
            }

            // HOOK: post upload callback
            if (isset($GLOBALS['TL_HOOKS']['postUpload']) && \is_array($GLOBALS['TL_HOOKS']['postUpload'])) {
                foreach ($GLOBALS['TL_HOOKS']['postUpload'] as $callback) {
                    if (\is_array($callback)) {
                        System::importStatic($callback[0])->{$callback[1]}($arrTargets);
                    } elseif (\is_callable($callback)) {
                        $callback($arrTargets);
                    }
                }
            }
        }
    }

    /**
     * @throws \Exception
     *
     * @return ResponseSuccess|DropzoneErrorResponse|void
     */
    public function upload()
    {
        $container = System::getContainer();
        $request = $container->get('huh.request');
        $uuids = [];
        $varReturn = null;
        // check for the request token
        if (!$request->hasPost('requestToken') || !RequestToken::validate($request->getPost('requestToken'))) {
            $objResponse = new DropzoneErrorResponse();
            $objResponse->setMessage('Invalid Request Token!');
            $objResponse->output();
        }

        if (!$request->files->has($this->name)) {
            return;
        }

        $tempUploadFolder = new Folder($container->getParameter('huh.multifileupload.upload_tmp'));

        // tmp directory is not public, mandatory for preview images
        $tmpPath = $this->container->getParameter('contao.web_dir').\DIRECTORY_SEPARATOR.$this->container->getParameter('huh.multifileupload.upload_tmp');

        if (!file_exists($tmpPath)
        ) {
            try {
                $tempUploadFolder->unprotect();
                $application = new Application($container->get('kernel'));
                $application->setAutoExit(false);

                $input = new ArrayInput([
                    'command' => 'contao:symlinks',
                ]);
                $output = new NullOutput();
                $application->run($input, $output);
            } catch (\Exception $e) {
                System::getContainer()->get('logger')->error('Error at running symlink command: '.$e->getMessage(), ['code' => $e->getCode()]);
                $objResponse = new DropzoneErrorResponse();
                $objResponse->setMessage('There was an error while uploading the file. Please contact the system administrator. Error Code: '.$e->getCode());
                $objResponse->output();
            }
        }

        $strField = $this->name;
        $varFile = $request->files->get($strField);
        // Multi-files upload at once
        if (\is_array($varFile)) {
            // prevent disk flooding
            if (\count($varFile) > $this->maxFiles) {
                $objResponse = new DropzoneErrorResponse();
                $objResponse->setMessage('Bulk file upload violation.');
                $objResponse->output();
            }

            /*
             * @var UploadedFile
             */
            foreach ($varFile as $strKey => $objFile) {
                $arrFile = $this->uploadFile($objFile, $tempUploadFolder->path);
                $varReturn[] = $arrFile;

                if (isset($varReturn['uuid']) && Validator::isUuid($arrFile['uuid'])) {
                    $uuids[] = $arrFile['uuid'];
                }
            }
        } else {
            // Single-file upload
            $varReturn = $this->uploadFile($varFile, $tempUploadFolder->path);

            if (isset($varReturn['uuid']) && Validator::isUuid($varReturn['uuid'])) {
                $uuids[] = $varReturn['uuid'];
            }
        }

        if (null !== $varReturn) {
            $this->varValue = $uuids;
            $objResponse = new ResponseSuccess();
            $objResult = new ResponseData();
            $objResult->setData($varReturn);
            $objResponse->setResult($objResult);

            return $objResponse;
        }
    }

    /**
     * @return string
     */
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
        $input = Input::decodeEntities($input);

        if ('' === $input || '[]' === $input) {
            $input = '[]';
        }

        $arrFiles = json_decode($input);
        $arrDeleted = json_decode(($this->getPost('deleted_'.$this->strName)));
        $blnEmpty = false;

        if (\is_array($arrFiles) && \is_array($arrDeleted)) {
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

        if (!$this->skipDeleteAfterSubmit && \is_array($arrDeleted)) {
            $this->deleteScheduledFiles($arrDeleted);
        }

        if (\is_array($arrFiles)) {
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
     * @return array
     */
    public function setAttributes(array $attributes)
    {
        if (isset($attributes['strTable']) && '' !== $attributes['strTable']) {
            $this->isSingleFile($attributes);
        }

        $attributes['uploadAction'] = $this->uploadAction;

        if (System::getContainer()->get('huh.utils.container')->isFrontend()) {
            $attributes['uploadActionParams'] = http_build_query(System::getContainer()->get('huh.ajax.action')->getParams(MultiFileUpload::NAME, $this->uploadAction));
        }

        $attributes['parallelUploads'] = 1; // in order to provide new token for each ajax request, upload one by one

        $attributes['addRemoveLinks'] = isset($attributes['addRemoveLinks']) ? $attributes['addRemoveLinks'] : true;

        $attributes['timeout'] = (int) (isset($attributes['timeout']) ? $attributes['timeout'] : (ini_get('max_execution_time') ?: 120)) * 1000;

        if (isset($attributes['value']) && !\is_array($attributes['value']) && !Validator::isBinaryUuid($attributes['value'])) {
            $attributes['value'] = json_decode($attributes['value']);
        }

        // bin to string -> never pass binary to the widget!!
        if (isset($attributes['value'])) {
            if (\is_array($attributes['value'])) {
                $attributes['value'] = array_map(function ($val) {
                    return Validator::isBinaryUuid($val) ? StringUtil::binToUuid($val) : $val;
                }, $attributes['value']);
            } else {
                $attributes['value'] = [
                    Validator::isBinaryUuid($attributes['value']) ? StringUtil::binToUuid($attributes['value']) : $attributes['value'],
                ];
            }
        }

        return $attributes;
    }

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

    protected function setVariables(array $attributes)
    {
        if (null !== $this->objUploader && \is_array($this->objUploader->getData())) {
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

        $strAllowed = $this->extensions ?: Config::get('uploadTypes');

        $arrAllowed = StringUtil::trimsplit(',', $strAllowed);

        $strExtension = strtolower($objUploadFile->getClientOriginalExtension());

        if (!$strExtension || !\is_array($arrAllowed) || !\in_array($strExtension, $arrAllowed)) {
            return sprintf($GLOBALS['TL_LANG']['ERR']['illegalFileExtension'], $strExtension);
        }

        // compare client mime type with mime type check result from server (e.g. user uploaded a php file with jpg extension)
        if (!$this->validateMimeType($objUploadFile->getClientMimeType(), $objUploadFile->getMimeType())) {
            return sprintf($GLOBALS['TL_LANG']['ERR']['illegalMimeType'], $objUploadFile->getMimeType());
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
     * Upload a file, store to $uploadFile and create database entry.
     *
     * @param UploadedFile $uploadFile   UploadedFile        The uploaded file object
     * @param string       $uploadFolder The upload target folder within contao files folder
     *
     * @return array Returns array with file information on success. Returns false if no valid file, file cannot be moved or destination lies outside the
     *               contao upload directory.
     */
    protected function uploadFile(UploadedFile $uploadFile, string $uploadFolder)
    {
        $container = System::getContainer();
        $originalFileName = rawurldecode($uploadFile->getClientOriginalName()); // e.g. double quotes are escaped with %22 -> decode it
        $originalFileNameEncoded = rawurlencode($originalFileName);
        $sanitizeFileName = $container->get('huh.utils.file')->sanitizeFileName($uploadFile->getClientOriginalName());

        if ($uploadFile->getError()) {
            return $this->prepareErrorArray($uploadFile->getError(), $originalFileNameEncoded, $sanitizeFileName);
        }

        $error = false;

        if (false !== ($error = $this->validateExtension($uploadFile))) {
            return $this->prepareErrorArray($error, $originalFileNameEncoded, $sanitizeFileName);
        }

        try {
            $this->validateImageSize($uploadFile);
        } catch (InvalidImageException $e) {
            return $this->prepareErrorArray($e->getMessage(), $originalFileNameEncoded, $sanitizeFileName);
        }

        $targetFileName = $container->get('huh.utils.file')->addUniqueIdToFilename($sanitizeFileName, static::UNIQID_PREFIX);

        try {
            $uploadFile = $uploadFile->move(TL_ROOT.'/'.$uploadFolder, $targetFileName);
        } catch (FileException $e) {
            return $this->prepareErrorArray(sprintf($GLOBALS['TL_LANG']['ERR']['moveUploadFile'], $e->getMessage()), $originalFileNameEncoded, $sanitizeFileName);
        }

        $relativePath = ltrim(str_replace(TL_ROOT, '', $uploadFile->getPathname()), \DIRECTORY_SEPARATOR);

        $file = null;
        $fileModel = null;

        try {
            // add db record
            $file = $container->get('contao.framework')->createInstance(File::class, [$relativePath]);
            $fileModel = $file->getModel();

            // Update the database
            if (null === $fileModel && $container->get('contao.framework')->getAdapter(Dbafs::class)->shouldBeSynchronized($relativePath)) {
                $fileModel = $container->get('contao.framework')->getAdapter(Dbafs::class)->addResource($relativePath);
            }

            if (!$file instanceof File || null === $fileModel) {
                // remove file from file system
                @unlink(TL_ROOT.'/'.$relativePath);

                return $this->prepareErrorArray($GLOBALS['TL_LANG']['ERR']['outsideUploadDirectory'], $originalFileNameEncoded, $sanitizeFileName);
            }

            $strUuid = $fileModel->uuid;
        } catch (\InvalidArgumentException $e) {
            // remove file from file system
            @unlink(TL_ROOT.'/'.$relativePath);

            return $this->prepareErrorArray($GLOBALS['TL_LANG']['ERR']['outsideUploadDirectory'], $originalFileNameEncoded, $sanitizeFileName);
        }

        // validateUploadCallback
        if (\is_array($this->validateUploadCallback)) {
            foreach ($this->validateUploadCallback as $callback) {
                if (!isset($callback[0]) || !class_exists($callback[0])) {
                    continue;
                }

                $objCallback = System::importStatic($callback[0]);

                if (!isset($callback[1]) || !method_exists($objCallback, $callback[1])) {
                    continue;
                }

                if ($errorCallback = $objCallback->{$callback[1]}($file, $this)) {
                    $error = $errorCallback;

                    break; // stop validation on first error
                }
            }
        }

        $arrData = [
            'filename' => $targetFileName,
            'filenameOrigEncoded' => $originalFileNameEncoded,
            'filenameSanitized' => $sanitizeFileName,
        ];

        if (false === $error && false !== ($arrInfo = $this->objUploader->prepareFile($strUuid))) {
            $arrData = array_merge($arrData, $arrInfo);

            return $arrData;
        }

        $arrData['error'] = $error;

        // remove invalid files from tmp folder
        if ($file instanceof File) {
            $file->delete();
        }

        return $arrData;
    }

    /**
     * @return array
     */
    protected function prepareErrorArray(string $error, string $originalFileNameEncoded, string $sanitizeFileName)
    {
        return [
            'error' => $error,
            'filenameOrigEncoded' => $originalFileNameEncoded,
            'filenameSanitized' => $sanitizeFileName,
        ];
    }

    private function validateImageSize(UploadedFile $upload)
    {
        $minWidth = $this->minImageWidth ?? 0;
        $minHeight = $this->minImageHeight ?? 0;
        $maxWidth = $this->maxImageWidth ?? Config::get('imageWidth') ?? 0;
        $maxHeight = $this->maxImageHeight ?? Config::get('imageHeight') ?? 0;

        if ($imageSize = @getimagesize($upload->getPathname())) {
            if ($minWidth > 0 && $imageSize[0] < $minWidth) {
                throw new InvalidImageException(sprintf($this->minImageWidthErrorText ?: $GLOBALS['TL_LANG']['ERR']['minWidth'], $minWidth, $imageSize[0]));
            }

            if ($minHeight > 0 && $imageSize[1] < $minHeight) {
                throw new InvalidImageException(sprintf($this->minImageHeightErrorText ?: $GLOBALS['TL_LANG']['ERR']['minHeight'], $minHeight, $imageSize[1]));
            }

            // Image exceeds maximum image width
            if ($maxWidth > 0 && $imageSize[0] > $maxWidth) {
                throw new InvalidImageException(sprintf($GLOBALS['TL_LANG']['ERR']['filewidth'], $upload->getClientOriginalName(), $maxWidth));
            }

            // Image exceeds maximum image height
            if ($maxHeight > 0 && $imageSize[1] > $maxHeight) {
                throw new InvalidImageException(sprintf($GLOBALS['TL_LANG']['ERR']['fileheight'], $upload->getClientOriginalName(), $maxHeight));
            }
        }
    }
}
