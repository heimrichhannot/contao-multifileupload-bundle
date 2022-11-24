<?php

/*
 * Copyright (c) 2022 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

use HeimrichHannot\MultiFileUploadBundle\Form\FormMultiFileUpload;
use HeimrichHannot\MultiFileUploadBundle\Widget\BackendMultiFileUpload;

/*
 * Front end form fields
 */
$GLOBALS['TL_FFL']['multifileupload'] = FormMultiFileUpload::class;
$GLOBALS['BE_FFL']['multifileupload'] = BackendMultiFileUpload::class;

/*
 * Ajax action
 */
$GLOBALS['AJAX'][\HeimrichHannot\MultiFileUploadBundle\Backend\MultiFileUpload::NAME] = [
    'actions' => [
        \HeimrichHannot\MultiFileUploadBundle\Backend\MultiFileUpload::ACTION_UPLOAD => [
            'arguments' => [],
            'optional' => [],
            'csrf_protection' => true, // cross-site request forgery (ajax token check)
        ],
    ],
];

/*
 * Assets (add dropzone not within contao files manager)
 */
$GLOBALS['TL_COMPONENTS']['multifileupload'] = [
    'js' => [
        'bundles/heimrichhannotcontaomultifileupload/assets/dropzone.js|static',
        'bundles/heimrichhannotcontaomultifileupload/assets/contao-multifileupload-bundle.js|static',
    ],
    'css' => [
        'bundles/heimrichhannotcontaomultifileupload/assets/dropzone.css|screen|static',
    ],
];
