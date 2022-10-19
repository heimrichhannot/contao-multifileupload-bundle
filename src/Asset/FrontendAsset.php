<?php

/*
 * Copyright (c) 2022 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\MultiFileUploadBundle\Asset;

use HeimrichHannot\EncoreContracts\PageAssetsTrait;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

class FrontendAsset implements ServiceSubscriberInterface
{
    use PageAssetsTrait;

    public function addFrontendAssets()
    {
        $this->addPageEntrypoint('contao-multifileupload-bundle', [
            'TL_CSS' => [
                'dropzone' => 'bundles/heimrichhannotcontaomultifileupload/assets/contao-multifileupload-bundle.css|screen|static',
            ],
            'TL_JAVASCRIPT' => [
                'dropzone' => 'bundles/heimrichhannotcontaomultifileupload/assets/dropzone.js|static',
                'multifileupload' => 'bundles/heimrichhannotcontaomultifileupload/assets/contao-multifileupload-bundle.js|static',
            ],
        ]);
    }
}
