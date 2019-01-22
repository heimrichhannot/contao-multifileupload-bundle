<?php

/*
 * Copyright (c) 2019 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\MultiFileUploadBundle\Widget;

use HeimrichHannot\MultiFileUploadBundle\Form\FormMultiFileUpload;

class BackendMultiFileUpload extends FormMultiFileUpload
{
    protected $uploadAction = 'multifileupload_upload';

    public function __construct($attributes = null)
    {
        parent::__construct($attributes);
    }
}
