<?php

/*
 * Copyright (c) 2022 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\MultiFileUploadBundle\Widget;

use HeimrichHannot\MultiFileUploadBundle\Form\FormMultiFileUpload;

class BackendMultiFileUpload extends FormMultiFileUpload
{
    protected $uploadAction = 'multifileupload_upload';

    protected $strTemplate = 'be_widget';

    public function __construct($attributes = null)
    {
        $GLOBALS['TL_CSS']['dropzone'] = 'bundles/heimrichhannotcontaomultifileupload/assets/contao-multifileupload-bundle.css|screen|static';
        $GLOBALS['TL_JAVASCRIPT']['dropzone'] = 'bundles/heimrichhannotcontaomultifileupload/assets/dropzone.js|static';
        $GLOBALS['TL_JAVASCRIPT']['multifileupload'] = 'bundles/heimrichhannotcontaomultifileupload/assets/contao-multifileupload-bundle.js|static';
        parent::__construct($attributes);
    }
}
