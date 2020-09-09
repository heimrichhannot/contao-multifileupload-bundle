<?php

/*
 * Copyright (c) 2020 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\MultiFileUploadBundle\Backend;

use Contao\BackendUser;
use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFrameworkInterface;
use Contao\Environment;
use Contao\File;
use Contao\FileUpload;
use Contao\FrontendTemplate;
use Contao\RequestToken;
use Contao\StringUtil;
use Contao\System;
use Contao\Widget;
use HeimrichHannot\MultiFileUploadBundle\Widget\BackendMultiFileUpload;

class MultiFileUpload extends FileUpload
{
    const NAME = 'multifileupload';
    const ACTION_UPLOAD = 'upload';
    const ACTION_UPLOAD_BACKEND = 'multifileupload_upload';
    const SESSION_ALLOWED_DOWNLOADS = 'multifileupload_allowed_downloads';
    const SESSION_FIELD_KEY = 'multifileupload_fields';

    /**
     * @var array
     */
    protected $data = [];

    /**
     * @var string
     */
    protected $template = 'form_multifileupload_dropzone';

    /**
     * @var string
     */
    protected $jQueryTemplate = 'j_multifileupload_dropzone';

    /**
     * @var BackendMultiFileUpload|Widget
     */
    protected $widget;

    /**
     * Has current page in xhtml type.
     *
     * @var bool
     */
    protected $isXhtml = false;

    /**
     * @var ContaoFrameworkInterface
     */
    protected $framework;

    /**
     * @var \Symfony\Component\DependencyInjection\ContainerInterface
     */
    protected $container;

    public function __construct(array $attributes, $widget = null)
    {
        parent::__construct();
        $this->container = System::getContainer();
        $this->framework = $this->container->get('contao.framework');
        $this->data = $attributes;
        $this->widget = $widget;

        $file = $this->container->get('huh.request')->getGet('file', true);

        // Send the file to the browser
        if (!empty($file)) {
            if (!$this->isAllowedDownload($file)) {
                header('HTTP/1.1 403 Forbidden');

                die('No file access.');
            }

            /** @var Controller $controller */
            $controller = $this->framework->getAdapter(Controller::class);
            $controller->sendFileToBrowser($file);
        }

        global $objPage;

        $this->isXhtml = ('xhtml' === $objPage->outputFormat);

        if (!isset($attributes['isSubmitCallback'])) {
            $this->loadDcaConfig();
        }
    }

    /**
     * Set an object property.
     *
     * @param string
     * @param mixed
     */
    public function __set(string $key, $value)
    {
        $this->data[$key] = $value;
    }

    /**
     * Return an object property.
     *
     * @param string
     *
     * @return mixed
     */
    public function __get($key)
    {
        switch ($key) {
            case 'name':
                return $this->strName;

                break;
        }

        if (isset($this->data[$key])) {
            return $this->data[$key];
        }

        return parent::__get($key);
    }

    /**
     * Check whether a property is set.
     *
     * @param string
     *
     * @return bool
     */
    public function __isset($key)
    {
        return isset($this->data[$key]);
    }

    /**
     * Generate the markup for the default uploader.
     *
     * @return string
     */
    public function generateMarkup()
    {
        $arrValues = array_values($this->value ?: []);

        $objT = new FrontendTemplate($this->template);
        $objT->setData($this->data);
        $objT->id = $this->id;
        $objT->uploadMultiple = $this->uploadMultiple;
        $objT->initialFiles = json_encode($arrValues);
        $objT->initialFilesFormatted = $this->prepareValue();
        $objT->uploadedFiles = '[]';
        $objT->deletedFiles = '[]';
        $objT->attributes = $this->getAttributes($this->getDropZoneOptions());
        $objT->widget = $this->widget;
        $objT->name = $this->widget->name;
        $objT->class = $objT->class.' '.$this->widget->name;
        $objT->class = trim($objT->class);

        $hideLabel = isset($this->data['hideLabel']) ? (bool) $this->data['hideLabel'] : false;

        $objT->hideLabel = !(!$hideLabel
                             && $this->container->get('huh.utils.container')->isFrontend());
        // store in session to validate on upload that field is allowed by user
        $fields = System::getContainer()->get('session')->get(static::SESSION_FIELD_KEY);
        $dca = $this->widget->arrDca;

        if (!$dca) {
            $dca = $GLOBALS['TL_DCA'][$this->widget->strTable]['fields'][$this->widget->strField];
        }
        $fields[$this->strTable][$this->id] = $dca;
        System::getContainer()->get('session')->set(static::SESSION_FIELD_KEY, $fields);

        return $objT->parse();
    }

    /**
     * Get a single dropzone option.
     *
     * @param string $key
     *
     * @return string
     */
    public function getDropZoneOption(&$key)
    {
        $varValue = null;

        switch ($key) {
            case 'url':
            case 'uploadAction':
            case 'uploadActionParams':
            case 'parallelUploads':
            case 'method':
            case 'withCredentials':
            case 'maxFiles':
            case 'uploadMultiple':
            case 'maxFilesize':
            case 'requestToken':
            case 'acceptedFiles':
            case 'addRemoveLinks':
            case 'thumbnailWidth':
            case 'thumbnailHeight':
            case 'previewsContainer':
                $varValue = $this->data[$key];

                break;

            case 'onchange':
                $varValue = System::getContainer()->get('huh.utils.container')->isBackend() ? $this->data[$key] : 'this.form.submit()';

                break;

            case 'createImageThumbnails':
                $varValue = ($this->thumbnailWidth || $this->thumbnailHeight && $this->data[$key]) ? 'true' : 'false';

                break;

            case 'name':
                $varValue = $this->data[$key];
                $key = 'paramName';

                break;

            case 'dictDefaultMessage':
            case 'dictFallbackMessage':
            case 'dictFallbackText':
            case 'dictInvalidFileType':
            case 'dictFileTooBig':
            case 'dictResponseError':
            case 'dictCancelUpload':
            case 'dictCancelUploadConfirmation':
            case 'dictRemoveFile':
            case 'dictMaxFilesExceeded':
                $varValue = \is_array($this->data[$key]) ? reset($this->data[$key]) : $this->data[$key];

                break;
        }

        return $varValue;
    }

    /**
     * @param $uuid
     *
     * @return array|bool
     */
    public function prepareFile($uuid)
    {
        if (null !== ($file = System::getContainer()->get('huh.utils.file')->getFileFromUuid($uuid)) && $file->exists()) {
            $this->addAllowedDownload($file->value);

            $arrReturn = [
                // remove timestamp from filename
                'name' => System::getContainer()->get('huh.utils.string')->pregReplaceLast('@_[a-f0-9]{13}@', $file->name),
                'uuid' => StringUtil::binToUuid($file->getModel()->uuid),
                'size' => $file->filesize,
            ];

            if (null !== ($strImage = $this->getPreviewImage($file))) {
                $arrReturn['dataURL'] = $strImage;
            }

            if (null !== ($strInfoUrl = $this->getInfoAction($file))) {
                $arrReturn['info'] = $strInfoUrl;
            }

            return $arrReturn;
        }

        return false;
    }

    public function addAllowedDownload(string $file)
    {
        $arrDownloads = System::getContainer()->get('session')->get(static::SESSION_ALLOWED_DOWNLOADS);

        if (!\is_array($arrDownloads)) {
            $arrDownloads = [];
        }

        $arrDownloads[] = $file;

        $arrDownloads = array_filter($arrDownloads);

        System::getContainer()->get('session')->set(static::SESSION_ALLOWED_DOWNLOADS, $arrDownloads);
    }

    /**
     * @param $file
     *
     * @return bool
     */
    public function isAllowedDownload($file)
    {
        $arrDownloads = System::getContainer()->get('session')->get(static::SESSION_ALLOWED_DOWNLOADS);

        if (!\is_array($arrDownloads)) {
            return false;
        }

        if (false !== array_search($file, $arrDownloads, true)) {
            return true;
        }

        return false;
    }

    /**
     * @return array
     */
    public function getData()
    {
        return $this->data;
    }

    protected function getByteSize($size)
    {
        // Convert the value to bytes
        if (false !== stripos($size, 'K')) {
            $size = round(explode('K', $size)[0] * 1024);
        } elseif (false !== stripos($size, 'M')) {
            $size = round(explode('M', $size)[0] * 1024 * 1024);
        } elseif (false !== stripos($size, 'G')) {
            $size = round(explode('G', $size)[0] * 1024 * 1024 * 1024);
        }

        return $size;
    }

    /**
     * Get maximum file size in bytes.
     *
     * @param null $maxUploadSize
     *
     * @throws \Exception For backend admin users, if widget upload size exceeds php.ini size or settings upload size
     *
     * @return mixed
     */
    protected function getMaximumUploadFileSize($maxUploadSize = null)
    {
        $intMaxUploadSizeDca = $this->getByteSize($maxUploadSize ?: 0);
        $intMaxUploadSizeSettings = $this->getByteSize(Config::get('maxFileSize') ?: 0);
        $intMaxUploadSizePhp = $this->getByteSize(ini_get('upload_max_filesize'));

        $strError = null;

        if ($intMaxUploadSizeDca > $intMaxUploadSizeSettings) {
            $strError = 'The maximum upload size you defined in the dca for the field '.$this->widget->name.' ('.$intMaxUploadSizeDca.' Bytes) exceeds the limit in tl_settings ('.$intMaxUploadSizeSettings.' Bytes)';
        } else {
            if ($intMaxUploadSizeDca > $intMaxUploadSizePhp) {
                $strError = 'The maximum upload size you defined in the dca for the field '.$this->widget->name.' ('.$intMaxUploadSizeDca.' Bytes) exceeds the limit in php.ini ('.$intMaxUploadSizePhp.' Bytes).';
            } else {
                if ($intMaxUploadSizeSettings > $intMaxUploadSizePhp) {
                    $strError = 'The maximum upload size you defined in tl_settings ('.$intMaxUploadSizeSettings.' Bytes) exceeds the limit in php.ini ('.$intMaxUploadSizePhp.' Bytes).';
                }
            }
        }

        // throw maximum upload size exceptions only in back end for admins/developer
        if (null !== $strError) {
            if (System::getContainer()->get('huh.utils.container')->isBackend() && System::getContainer()->get('contao.framework')->createInstance(BackendUser::class)->isAdmin) {
                throw new \Exception($strError);
            }
            System::getContainer()->get('monolog.logger.contao')->log(TL_ERROR, $strError);
        }

        if (!$intMaxUploadSizeDca && !$intMaxUploadSizeSettings) {
            return $intMaxUploadSizePhp;
        } elseif (!$intMaxUploadSizeDca) {
            return min($intMaxUploadSizeSettings, $intMaxUploadSizePhp);
        } elseif (!$intMaxUploadSizeSettings) {
            return min($intMaxUploadSizeDca, $intMaxUploadSizePhp);
        }

        return min($intMaxUploadSizeDca, $intMaxUploadSizeSettings, $intMaxUploadSizePhp);
    }

    protected function loadDcaConfig()
    {
        // in MiB
        $this->maxFilesize = round(($this->getMaximumUploadFileSize($this->maxUploadSize) / 1024 / 1024), 2);

        $this->acceptedFiles = implode(
            ',',
            array_map(
                function ($a) {
                    return '.'.$a;
                },
                StringUtil::trimsplit(',', strtolower($this->extensions ?: Config::get('uploadTypes')))
            )
        );

        // labels & messages
        $this->labels = $this->labels ?: $GLOBALS['TL_LANG']['MSC']['dropzone']['labels'];
        $this->messages = $this->messages ?: $GLOBALS['TL_LANG']['MSC']['dropzone']['messages'];

        foreach ($this->messages as $strKey => $strMessage) {
            $this->{$strKey} = $strMessage;
        }

        foreach ($this->labels as $strKey => $strMessage) {
            $this->{$strKey} = $strMessage;
        }

        $this->thumbnailWidth = $this->thumbnailWidth ?: 90;
        $this->thumbnailHeight = $this->thumbnailHeight ?: 90;

        $this->createImageThumbnails = $this->createImageThumbnails ?: true;

        $this->requestToken = RequestToken::get();

        $this->previewsContainer = '#ctrl_'.$this->id.' .dropzone-previews';

        $this->uploadMultiple = ('checkbox' === $this->fieldType);

        $maxFilesDefault = 1;

        if (System::getContainer()->hasParameter('huh.multifileupload.max_files_default')) {
            $maxFilesDefault = System::getContainer()->getParameter('huh.multifileupload.max_files_default');
        }

        $this->maxFiles = ($this->uploadMultiple ? ($this->maxFiles ?: $maxFilesDefault) : 1);
    }

    /**
     * Return data attributes in correct syntax, considering doc type.
     *
     * @return string
     */
    protected function getAttributes(array $attributes = [])
    {
        $arrOptions = [];

        foreach ($attributes as $strKey => $varValue) {
            $arrOptions[] = $this->getAttribute($strKey, $varValue);
        }

        return implode(' ', $arrOptions);
    }

    /**
     * Return html attribute in correct syntax, considering doc type.
     *
     * @return string
     */
    protected function getAttribute(string $key, string $value)
    {
        if ('disabled' === $key || 'readonly' === $key || 'required' === $key || 'autofocus' === $key || 'multiple' === $key) {
            $value = $key;

            return $this->isXhtml ? ' '.$key.'="'.$value.'"' : ' '.$key;
        }

        return ' '.$key.'="'.$value.'"';
    }

    /**
     * Get all dropzone related options.
     *
     * @return array
     */
    protected function getDropZoneOptions()
    {
        $arrOptions = [];

        foreach (array_keys($this->data) as $strKey) {
            if (null === ($varValue = $this->getDropZoneOption($strKey))) {
                continue;
            }

            // convert camelCase to hyphen, jquery.data() will make camelCase from hyphen again
            $strKey = 'data-'.strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $strKey));

            $arrOptions[$strKey] = $varValue;
        }

        return $arrOptions;
    }

    protected function prepareValue()
    {
        if (!empty($this->value)) {
            $arrResult = [];

            foreach ($this->value as $strUuid) {
                if ($arrFile = $this->prepareFile($strUuid)) {
                    $arrResult[] = $arrFile;
                }
            }

            return json_encode($arrResult);
        }
    }

    /**
     * @return string|null
     */
    protected function getInfoAction(File $file)
    {
        $strUrl = null;
        $strFileNameEncoded = StringUtil::convertEncoding($file->name, Config::get('characterSet'));
        $containerUtils = System::getContainer()->get('huh.utils.container');

        if ($containerUtils->isFrontend()) {
            $strHref = System::getContainer()->get('huh.ajax.action')->removeAjaxParametersFromUrl(Environment::get('uri'));
            $strHref .= ((Config::get('disableAlias') || false !== strpos($strHref, '?')) ? '&' : '?').'file='.System::urlEncode($file->value);

            return 'window.open("'.$strHref.'", "_blank");';
        } elseif ($containerUtils->isBackend()) {
            $popupWidth = 664;
            $popupHeight = 299;

            $href = System::getContainer()->get('router')->generate('contao_backend_popup', ['src' => base64_encode($file->value)]);

            return 'Backend.openModalIframe({"width":"'.$popupWidth.'","title":"'.str_replace("'", "\\'", StringUtil::specialchars($strFileNameEncoded, false, true)).'","url":"'.$href.'","height":"'.$popupHeight.'"});';
        }

        return $strUrl;
    }

    /**
     * @return string|null
     */
    protected function getPreviewImage(File $file)
    {
        if ($file->isImage && !$this->mimeThumbnailsOnly) {
            return $file->path;
        }

        $themeFolder = rtrim($this->mimeFolder ?: System::getContainer()->getParameter('huh.multifileupload.mime_theme_default'), '/');

        if (!file_exists(TL_ROOT.'/'.$themeFolder.'/mimetypes.json')) {
            return null;
        }

        $objMimeFile = new File($themeFolder.'/mimetypes.json');

        $objMines = json_decode($objMimeFile->getContent());

        if (!property_exists($objMines, $file->extension)) {
            return null;
        }

        if (!file_exists(TL_ROOT.'/'.$themeFolder.'/'.$objMines->{$file->extension})) {
            return null;
        }

        return $themeFolder.'/'.$objMines->{$file->extension};
    }
}
