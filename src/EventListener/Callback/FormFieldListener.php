<?php

/*
 * Copyright (c) 2023 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\MultiFileUploadBundle\EventListener\Callback;

use Contao\CoreBundle\ServiceAnnotation\Callback;
use Contao\DataContainer;
use Contao\FormFieldModel;

class FormFieldListener
{
    /**
     * @Callback(table="tl_form_field", target="config.onload")
     */
    public function onLoadCallback(DataContainer $dc = null): void
    {
        if (!$dc || !$dc->id) {
            return;
        }

        $formFieldModel = FormFieldModel::findByPk($dc->id);

        if (!$formFieldModel || 'multifileupload' !== $formFieldModel->type) {
            return;
        }

        $GLOBALS['TL_DCA']['tl_form_field']['fields']['uploadFolder']['eval']['mandatory'] = true;
    }
}
