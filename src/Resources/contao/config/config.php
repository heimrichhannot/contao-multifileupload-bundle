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
$GLOBALS['TL_FFL']['multifileupload'] = 'HeimrichHannot\MultiFileUploadBundle\Form\FormMultiFileUpload';
$GLOBALS['BE_FFL']['multifileupload'] = 'HeimrichHannot\MultiFileUploadBundle\Widget\BackendMultiFileUpload';

/**
 * Hooks
 */
$GLOBALS['TL_HOOKS']['executePostActions']['multifileupload'] = ['HeimrichHannot\MultiFileUploadBundle\Widget\BackendMultiFileUpload', 'executePostActionsHook'];

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
        'assets/dropzone-latest/dist/min/dropzone.min.js|static',
        'system/modules/multifileupload/assets/js/multifileupload.min.js|static',
    ],
    'css' => [
        'system/modules/multifileupload/assets/css/dropzone.min.css|screen|static',
    ],
];

if (TL_MODE == 'FE') {
    $GLOBALS['TL_CSS']['dropzone'] = 'system/modules/multifileupload/assets/css/dropzone.min.css|screen|static';

    $GLOBALS['TL_JAVASCRIPT']['dropzone']        = 'assets/dropzone-latest/dist/min/dropzone.min.js|static';
    $GLOBALS['TL_JAVASCRIPT']['multifileupload'] = 'system/modules/multifileupload/assets/js/multifileupload.min.js|static';
}

if (TL_MODE == 'BE' && \Input::get('do') != 'files') {

    $GLOBALS['TL_CSS']['dropzone'] = 'system/modules/multifileupload/assets/css/dropzone.min.css|screen|static';

    $GLOBALS['TL_JAVASCRIPT']['dropzone']        = 'assets/dropzone-latest/dist/min/dropzone.min.js|static';
    $GLOBALS['TL_JAVASCRIPT']['multifileupload'] = 'system/modules/multifileupload/assets/js/multifileupload.min.js';
}
