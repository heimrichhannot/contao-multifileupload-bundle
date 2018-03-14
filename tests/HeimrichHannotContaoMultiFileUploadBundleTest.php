<?php

/*
 * Copyright (c) 2018 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\MultiFileUploadBundle\Tests;

use HeimrichHannot\MultiFileUploadBundle\DependencyInjection\MultiFileUploadExtension;
use HeimrichHannot\MultiFileUploadBundle\HeimrichHannotContaoMultiFileUploadBundle;
use PHPUnit\Framework\TestCase;

class HeimrichHannotContaoMultiFileUploadBundleTest extends TestCase
{
    public function testGetContainerExtension()
    {
        $bundle = new HeimrichHannotContaoMultiFileUploadBundle();

        $this->assertInstanceOf(MultiFileUploadExtension::class, $bundle->getContainerExtension());
    }
}
