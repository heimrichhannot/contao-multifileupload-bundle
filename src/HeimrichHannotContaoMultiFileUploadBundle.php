<?php

/*
 * Copyright (c) 2019 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\MultiFileUploadBundle;

use HeimrichHannot\MultiFileUploadBundle\DependencyInjection\MultiFileUploadExtension;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class HeimrichHannotContaoMultiFileUploadBundle extends Bundle
{
    /**
     * @return MultiFileUploadExtension
     */
    public function getContainerExtension()
    {
        return new MultiFileUploadExtension();
    }
}
