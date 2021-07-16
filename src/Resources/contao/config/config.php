<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

/*
 * Front end form fields
 */

use HeimrichHannot\MultiFileUploadBundle\EventListener\Contao\ExecutePostActionsListener;
use HeimrichHannot\SmulRegionalportalBundle\EventListener\Contao\GetAttributesFromDcaListener;

$GLOBALS['TL_FFL']['multifileupload'] = \HeimrichHannot\MultiFileUploadBundle\Form\FormMultiFileUpload::class;
$GLOBALS['BE_FFL']['multifileupload'] = \HeimrichHannot\MultiFileUploadBundle\Widget\BackendMultiFileUpload::class;

/*
 * Hooks
 */
$GLOBALS['TL_HOOKS']['executePostActions']['multifileupload'] = [ExecutePostActionsListener::class, '__invoke'];
$GLOBALS['TL_HOOKS']['getAttributesFromDca']['huh_multifileupload'] = [GetAttributesFromDcaListener::class, '__invoke'];

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
