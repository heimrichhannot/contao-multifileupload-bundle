<?php

/*
 * Copyright (c) 2018 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\MultiFileUploadBundle\Widget;

use Contao\BackendUser;
use Contao\DataContainer;
use Contao\System;
use Contao\Widget;
use HeimrichHannot\AjaxBundle\Response\Response;
use HeimrichHannot\AjaxBundle\Response\ResponseError;
use HeimrichHannot\MultiFileUploadBundle\Backend\MultiFileUpload;
use HeimrichHannot\MultiFileUploadBundle\Form\FormMultiFileUpload;

class BackendMultiFileUpload extends FormMultiFileUpload
{
    protected $uploadAction = 'multifileupload_upload';

    public function __construct($attributes = null)
    {
        parent::__construct($attributes);
    }

    public function executePostActionsHook(string $action, DataContainer $dc)
    {
        if ($action !== $this->uploadAction) {
            return false;
        }
        $container = System::getContainer();
        $request = $container->get('huh.request');

        $fields = $container->get('session')->get(MultiFileUpload::SESSION_FIELD_KEY);

        // Check whether the field is allowed for regular users
        if (!isset($fields[$dc->table][$request->getPost('field')]) || (!isset($fields[$dc->table]['fields'][$request->getPost('field')]['exclude']) && !BackendUser::getInstance()->hasAccess($dc->table.'::'.$request->getPost('field'), 'alexf'))) {
            $container->get('monolog.logger.contao')->log('Field "'.$request->getPost('field').'" is not an allowed selector field (possible SQL injection attempt)', __METHOD__, TL_ERROR);

            $objResponse = new ResponseError();
            $objResponse->setMessage('Bad Request');
            $objResponse->output();
        }

        if (null === $dc->activeRecord) {
            $dc->activeRecord = $container->get('huh.utils.model')->findModelInstancesBy($dc->table, [$dc->table.'.id'], [$dc->id]);
        }

        // add dca attributes and instantiate current object to set widget attributes
        $attributes = $container->get('contao.framework')->getAdapter(Widget::class)->getAttributesFromDca($fields[$dc->table][$request->getPost('field')], $request->getPost('field'));
        $objUploader = new self($attributes);
        $objResponse = $objUploader->upload();

        /* @var Response */
        if ($objResponse instanceof Response) {
            $objResponse->output();
        }
    }
}
