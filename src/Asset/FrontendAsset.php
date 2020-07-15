<?php
/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2020 Heimrich & Hannot GmbH
 *
 * @author  Thomas Körner <t.koerner@heimrich-hannot.de>
 * @license http://www.gnu.org/licences/lgpl-3.0.html LGPL
 */


namespace HeimrichHannot\MultiFileUploadBundle\Asset;


use HeimrichHannot\UtilsBundle\Container\ContainerUtil;

class FrontendAsset
{
    /**
     * @var ContainerUtil
     */
    private $containerUtil;
    /**
     * @var \HeimrichHannot\EncoreBundle\Asset\FrontendAsset
     */
    protected $encoreFrontendAsset;


    /**
     * FrontendAsset constructor.
     */
    public function __construct(ContainerUtil $containerUtil)
    {
        $this->containerUtil = $containerUtil;
    }

    /**
     * @param \HeimrichHannot\EncoreBundle\Asset\FrontendAsset $encoreFrontendAsset
     */
    public function setEncoreFrontendAsset(\HeimrichHannot\EncoreBundle\Asset\FrontendAsset $encoreFrontendAsset): void
    {
        $this->encoreFrontendAsset = $encoreFrontendAsset;
    }

    public function addFrontendAssets()
    {
        if ($this->containerUtil->isFrontend() && $this->encoreFrontendAsset) {
            $this->encoreFrontendAsset->addActiveEntrypoint('contao-multifileupload-bundle');
        }

        $GLOBALS['TL_CSS']['dropzone']               = 'bundles/heimrichhannotcontaomultifileupload/assets/contao-multifileupload-bundle.css|screen|static';
        $GLOBALS['TL_JAVASCRIPT']['dropzone']        = 'bundles/heimrichhannotcontaomultifileupload/assets/dropzone.js|static';
        $GLOBALS['TL_JAVASCRIPT']['multifileupload'] = 'bundles/heimrichhannotcontaomultifileupload/assets/contao-multifileupload-bundle.js|static';
    }
}