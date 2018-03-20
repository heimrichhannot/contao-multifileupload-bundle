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
use HeimrichHannot\MultiFileUpload\Backend\MultiFileUpload;
use HeimrichHannot\MultiFileUpload\Form\FormMultiFileUpload;

class BackendMultiFileUpload extends FormMultiFileUpload
{
    protected $uploadAction = 'multifileupload_upload';

    public function __construct($attributes = null)
    {
        parent::__construct($attributes);
    }

    public function executePostActionsHook($strAction, DataContainer $dc)
    {
        if ($strAction !== $this->uploadAction) {
            return false;
        }

        $fields = System::getContainer()->get('session')->get(MultiFileUpload::SESSION_FIELD_KEY);

        // Check whether the field is allowed for regular users
        if (!isset($fields[$dc->table][System::getContainer()->get('huh.request')->getPost('field')]) || (!isset($fields[$dc->table]['fields'][System::getContainer()->get('huh.request')->getPost('field')]['exclude']) && !BackendUser::getInstance()->hasAccess($dc->table.'::'.System::getContainer()->get('huh.request')->getPost('field'), 'alexf'))) {
            System::getContainer()->get('monolog.logger.contao')->log('Field "'.System::getContainer()->get('huh.request')->getPost('field').'" is not an allowed selector field (possible SQL injection attempt)', __METHOD__, TL_ERROR);

            $objResponse = new ResponseError();
            $objResponse->setMessage('Bad Request');
            $objResponse->output();
        }

        if (null === $dc->activeRecord) {
            $dc->activeRecord = System::getContainer()->get('huh.utils.model')->findModelInstancesBy($dc->table, ['id'], [$dc->id]);
        }

        // add dca attributes and instantiate current object to set widget attributes
        $arrAttributes = System::getContainer()->get('contao.framework')->getAdapter(Widget::class)->getAttributesFromDca($fields[$dc->table][System::getContainer()->get('huh.request')->getPost('field')], System::getContainer()->get('huh.request')->getPost('field'));
        $objUploader = new self($arrAttributes);
        $objResponse = $objUploader->upload();

        /* @var Response */
        if ($objResponse instanceof Response) {
            $objResponse->output();
        }
    }
}
