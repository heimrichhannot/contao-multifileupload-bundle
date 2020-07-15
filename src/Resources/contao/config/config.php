<?php
/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2015 Heimrich & Hannot GmbH
 *
 * @package multifileupload
 * @author  Rico Kaltofen <r.kaltofen@heimrich-hannot.de>
 * @license http://www.gnu.org/licences/lgpl-3.0.html LGPL
 */


/**
 * Front end form fields
 */
$GLOBALS['TL_FFL']['multifileupload'] = \HeimrichHannot\MultiFileUploadBundle\Form\FormMultiFileUpload::class;
$GLOBALS['BE_FFL']['multifileupload'] = \HeimrichHannot\MultiFileUploadBundle\Widget\BackendMultiFileUpload::class;

/**
 * Hooks
 */
$GLOBALS['TL_HOOKS']['executePostActions']['multifileupload'] = ['huh.multifileupload.listener.hooks', 'executePostActionsHook'];
$GLOBALS['TL_HOOKS']['getAttributesFromDca']['huh_multifileupload'] = [\HeimrichHannot\MultiFileUploadBundle\EventListener\GetAttributesFromDcaListener::class, 'onGetAttributesFromDca'];

/**
 * Ajax action
 */
$GLOBALS['AJAX'][\HeimrichHannot\MultiFileUploadBundle\Backend\MultiFileUpload::NAME] = [
    'actions' => [
        \HeimrichHannot\MultiFileUploadBundle\Backend\MultiFileUpload::ACTION_UPLOAD => [
            'arguments'       => [],
            'optional'        => [],
            'csrf_protection' => true, // cross-site request forgery (ajax token check)
        ],
    ],
];

/**
 * Assets (add dropzone not within contao files manager)
 */
$GLOBALS['TL_COMPONENTS']['multifileupload'] = [
    'js'  => [
        'bundles/heimrichhannotcontaomultifileupload/assets/dropzone.js|static',
        'bundles/heimrichhannotcontaomultifileupload/assets/contao-multifileupload-bundle.js|static',
    ],
    'css' => [
        'bundles/heimrichhannotcontaomultifileupload/assets/dropzone.css|screen|static',
    ],
];