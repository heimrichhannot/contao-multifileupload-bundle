<?php

/*
 * Copyright (c) 2020 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\MultiFileUploadBundle\EventListener;

use Contao\Form;
use Contao\Input;
use Contao\Widget;
use HeimrichHannot\MultiFileUploadBundle\Form\FormMultiFileUpload;

class ValidateFormFieldListener
{
    public function __invoke(Widget $widget, string $formId, array $formData, Form $form): Widget
    {
        if (!($widget instanceof FormMultiFileUpload) || $widget->hasErrors() || !Input::post('FORM_SUBMIT') == $formId) {
            return $widget;
        }
//        $widget->moveFiles()

//        if ('myform' === $formId && $widget instanceof \Contao\FormTextField && 'mywidget' === $widget->name) {
//            // Do your custom validation and add an error if widget does not validate
        ////            if (!$this->validateWidget($widget)) {
        ////                $widget->addError('My custom widget error');
        ////            }
//        }

        return $widget;
    }
}
