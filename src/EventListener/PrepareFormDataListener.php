<?php

/*
 * Copyright (c) 2020 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\MultiFileUploadBundle\EventListener;

use Contao\FilesModel;
use Contao\Form;
use Contao\FormFieldModel;
use Contao\Validator;
use HeimrichHannot\MultiFileUploadBundle\Backend\MultiFileUpload;
use HeimrichHannot\MultiFileUploadBundle\Form\FormMultiFileUpload;

class PrepareFormDataListener
{
    /**
     * @param array|FormFieldModel[] $fields
     */
    public function __invoke(array &$submittedData, array $labels, array $fields, Form $form): void
    {
//        foreach ($fields as $field) {
//            if (!'multifileupload' === $field->type || !is_array($submittedData[$field->name])) {
//                continue;
//            }
//            $widget = new FormMultiFileUpload($field->row());
//            $widget->moveFiles()
//
//
//
//
//            $files = FilesModel::findMultipleByUuids($submittedData[$field->name]);
//            foreach ($submittedData[$field->name] as $value) {
//                if (!Validator::isUuid($value)) {
//                    continue;
//                }
//                $file = FilesModel::findByUuid($value);
//                if (!$file) {
//                    continue;
//                }
//                if (!isset($_SESSION['FILES'][$field->name])) {
//                    $_SESSION['FILES'][$field->name] = [
//                        "name"     => $file->name,
//                        "type"     => $file->type,
//                        "tmp_name" => $file->path,
//                        "error"    => 0
//                    ];
//                }
//            }
//            return;
//        }
    }
}
