<?php

/*
 * Copyright (c) 2019 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\MultiFileUploadBundle\Response;

use HeimrichHannot\AjaxBundle\Response\ResponseError;

class DropzoneErrorResponse extends ResponseError
{
    public function getOutputData()
    {
        $outputData = parent::getOutputData();
        $outputData->error = $outputData->message;

        return $outputData;
    }
}
