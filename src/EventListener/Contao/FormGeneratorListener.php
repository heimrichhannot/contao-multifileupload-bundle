<?php

/*
 * Copyright (c) 2022 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\MultiFileUploadBundle\EventListener\Contao;

use Contao\CoreBundle\ServiceAnnotation\Hook;
use Contao\Form;

class FormGeneratorListener
{
    /**
     * @Hook("processFormData", priority=10)
     */
    public function onProcessFormData(array &$submittedData, array $formData, ?array $files, array $labels, Form $form): void
    {
        if (empty($files)) {
            return;
        }

        // Remove multifileupload values from submitted data, as it is available from
        // files and lead to issues and unneeded tokens in notification center
        foreach ($files as $field => $fileData) {
            if (!($fileData['multifileupload'] ?? false)) {
                continue;
            }

            if (!isset($fileData['field']) || !isset($fileData['key'])) {
                continue;
            }

            if (isset($submittedData[$fileData['field']][$fileData['key']])) {
                unset($submittedData[$fileData['field']][$fileData['key']]);
            }

            if (empty($submittedData[$fileData['field']])) {
                unset($submittedData[$fileData['field']]);

                break;
            }
        }
    }
}
