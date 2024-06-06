<?php

/*
 * Copyright (c) 2023 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\MultiFileUploadBundle\Form;

use Contao\Database;
use Contao\FilesModel;
use Contao\Input;
use Contao\StringUtil;
use Contao\System;
use Contao\Upload;
use Contao\Validator;
use HeimrichHannot\MultiFileUploadBundle\Asset\FrontendAsset;
use HeimrichHannot\MultiFileUploadBundle\Backend\MultiFileUpload;
use HeimrichHannot\MultiFileUploadBundle\Controller\UploadController;
use HeimrichHannot\MultiFileUploadBundle\EventListener\Callback\OnSubmitCallbackListener;
use HeimrichHannot\MultiFileUploadBundle\Exception\NoUploadException;
use HeimrichHannot\MultiFileUploadBundle\File\FilesHandler;
use HeimrichHannot\MultiFileUploadBundle\Upload\UploadConfiguration;
use HeimrichHannot\UtilsBundle\Image\ImageUtil;
use HeimrichHannot\UtilsBundle\Util\Utils;

class FormMultiFileUpload extends Upload
{
    public const UNIQID_PREFIX = 'mfuid';

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
        if ($this->isFormGeneratorBackedView()) {
            return;
        }

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
        $attributes = $this->setFormGeneratorAttributes($attributes);

        parent::__construct($attributes);

        // form generator fix, here the id of the field is
        if (is_numeric($attributes['id'] ?? '')) {
            $attributes['id'] = $attributes['name'];
        }

        $this->objUploader = new MultiFileUpload($attributes, $this);

        $this->container->get(FrontendAsset::class)->addFrontendAssets();

        $this->setVariables($attributes);

        if ($this->strTable) {
            // add onsubmit_callback at first onsubmit_callback position: move files after form submission
            $GLOBALS['TL_DCA'][$this->strTable]['config']['onsubmit_callback'] = array_merge(
                ['multifileupload_moveFiles' => [OnSubmitCallbackListener::class, 'moveFiles']],
                ($GLOBALS['TL_DCA'][$this->strTable]['config']['onsubmit_callback'] ?? [])
            );
        }

        $request = System::getContainer()->get('request_stack')->getCurrentRequest();

        if (
            $request->isXmlHttpRequest()
            && \in_array($request->request->get('action', ''), [
                MultiFileUpload::ACTION_UPLOAD_BACKEND, MultiFileUpload::ACTION_UPLOAD,
            ])
            && (System::getContainer()->get(Utils::class)->container()->isBackend()
                ? ($request->request->get('field', '') === $this->name)
                : true
            )
        ) {



            $uploadConfig = new UploadConfiguration();
            $uploadConfig->maxFiles = $this->maxFiles;
            if (is_string($this->extensions)) {
                $this->extensions = explode(',', $this->extensions);
            } else {
                $this->extensions = $this->extensions ?? [];
            }

            $uploadConfig->mimeTypes = $this->mimeTypes ?? [];
            $uploadConfig->minImageWidth = $this->minImageWidth ?? 0;
            $uploadConfig->minImageHeight = $this->minImageHeight ?? 0;
            $uploadConfig->maxImageWidth = $this->maxImageWidth ?? 0;
            $uploadConfig->maxImageHeight = $this->maxImageHeight ?? 0;
            $uploadConfig->minImageWidthErrorText = $this->minImageWidthErrorText ?? null;
            $uploadConfig->minImageHeightErrorText = $this->minImageHeightErrorText ?? null;
            $uploadConfig->validateUploadCallback = $this->validateUploadCallback ?? [];

            try {
                $response = $this->container->get(UploadController::class)->upload(
                    $request,
                    $this->name,
                    $this->objUploader,
                    $uploadConfig
                );
                $response->send();

                exit;
            } catch (NoUploadException $e) {
            }
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

        if (!$this->strTable && !empty($arrFiles)) {
            $uploadFolder = FilesModel::findByUuid($this->uploadFolder);

            if (null === $uploadFolder) {
                throw new \Exception('Invalid upload folder ID '.$this->uploadFolder);
            }

            System::getContainer()->get(FilesHandler::class)->moveUploads($arrFiles, $uploadFolder->path, '', $this->strName);
        }

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

        if (!\is_array($arrFiles)) {
            $arrFiles = [$arrFiles];
        }

        $fileUtil = System::getContainer()->get('huh.utils.file');
        $projectDir = System::getContainer()->getParameter('kernel.project_dir');

        foreach ($arrFiles as $k => $v) {
            if (!Validator::isUuid($v)) {
                $this->addError($GLOBALS['TL_LANG']['ERR']['invalidUuid']);

                return false;
            }

            if (null === ($file = $fileUtil->getFileFromUuid($v)) || !$file->exists()) {
                unset($arrFiles[$k]);

                continue;
            }

            $arrFiles[$k] = StringUtil::uuidToBin($v);

            if (System::getContainer()->get(Utils::class)->container()->isFrontend()) {
                $_SESSION['FILES'][$this->strName.'__'.$k] = [
                    'name' => $file->name,
                    'type' => $file->mime,
                    'tmp_name' => $projectDir.'/'.$file->path,
                    'error' => 0,
                    'size' => $file->size,
                    'uploaded' => true,
                    'uuid' => $v,
                    'multifileupload' => true,
                    'field' => $this->strName,
                    'key' => $k,
                ];
            }
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
        $container = System::getContainer();

        if (isset($attributes['minImageWidth']) && !\is_int($attributes['minImageWidth'])) {
            $attributes['minImageWidth'] = System::getContainer()->get(ImageUtil::class)->getPixelValue($attributes['minImageWidth']);
        }

        if (isset($attributes['maxImageWidth']) && !\is_int($attributes['maxImageWidth'])) {
            $attributes['maxImageWidth'] = System::getContainer()->get(ImageUtil::class)->getPixelValue($attributes['maxImageWidth']);
        }

        if (isset($attributes['minImageHeight']) && !\is_int($attributes['minImageHeight'])) {
            $attributes['minImageHeight'] = System::getContainer()->get(ImageUtil::class)->getPixelValue($attributes['minImageHeight']);
        }

        if (isset($attributes['maxImageHeight']) && !\is_int($attributes['maxImageHeight'])) {
            $attributes['maxImageHeight'] = System::getContainer()->get(ImageUtil::class)->getPixelValue($attributes['maxImageHeight']);
        }

        if (isset($attributes['strTable']) && '' !== $attributes['strTable']) {
            $this->isSingleFile($attributes);
        }

        $attributes['uploadAction'] = $this->uploadAction;

        if (System::getContainer()->get(Utils::class)->container()->isFrontend()) {
            $attributes['uploadActionParams'] = '';
        }

        $attributes['parallelUploads'] = 1; // in order to provide new token for each ajax request, upload one by one

        $attributes['addRemoveLinks'] = isset($attributes['addRemoveLinks']) ? $attributes['addRemoveLinks'] : true;

        $attributes['timeout'] = (int) (isset($attributes['timeout']) ? $attributes['timeout'] : (ini_get('max_execution_time') ?: 120)) * 1000;

        if (isset($attributes['value']) && !\is_array($attributes['value']) && !Validator::isBinaryUuid($attributes['value'])) {
            $value = json_decode($attributes['value']);

            if (!$value) {
                // Fix encoded json array values
                $value = json_decode(html_entity_decode($attributes['value']));
            }
            $attributes['value'] = $value;
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

    private function setFormGeneratorAttributes(array $attributes): array
    {
        if (isset($attributes['mf_maxFiles']) && is_numeric($attributes['mf_maxFiles'])) {
            $attributes['maxFiles'] = (int) $attributes['mf_maxFiles'];

            if ($attributes['maxFiles'] !== 1) {
                $attributes['fieldType'] = 'checkbox';
            }
        }

        if (isset($attributes['mf_maxFileSize']) && is_numeric($attributes['mf_maxFileSize'])) {
            $attributes['maxUploadSize'] = (int) $attributes['mf_maxFileSize'].'M';
        }

        return $attributes;
    }

    public function parse($arrAttributes = null)
    {
        if ($this->isFormGeneratorBackedView()) {
            return '<div class="additionalFiles">
                <div class="multifileupload dropzone" style="padding:5px;">
                <input type="file" name="' . $this->strName . '[]" class="tl_upload_field" onfocus="Backend.getScrollOffset()" multiple>
                </div>
            </div>';
        }

        return parent::parse($arrAttributes);
    }

    private function isFormGeneratorBackedView(): bool
    {
        return (System::getContainer()->get(Utils::class)->container()->isBackend() && Input::get('do') === 'form' && Input::get('act') !== 'edit');
    }


}
