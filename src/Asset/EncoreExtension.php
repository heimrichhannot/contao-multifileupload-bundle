<?php

/*
 * Copyright (c) 2022 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\MultiFileUploadBundle\Asset;

use HeimrichHannot\EncoreContracts\EncoreEntry;
use HeimrichHannot\MultiFileUploadBundle\HeimrichHannotContaoMultiFileUploadBundle;

class EncoreExtension implements \HeimrichHannot\EncoreContracts\EncoreExtensionInterface
{
    /**
     * {@inheritDoc}
     */
    public function getBundle(): string
    {
        return HeimrichHannotContaoMultiFileUploadBundle::class;
    }

    /**
     * {@inheritDoc}
     */
    public function getEntries(): array
    {
        return [
            EncoreEntry::create('contao-multifileupload-bundle', 'src/Resources/assets/js/contao-multifileupload-bundle.js')
                ->setRequiresCss(true)
                ->addJsEntryToRemoveFromGlobals('multifileupload')
                ->addJsEntryToRemoveFromGlobals('dropzone')
                ->addCssEntryToRemoveFromGlobals('dropzone'),
        ];
    }
}
