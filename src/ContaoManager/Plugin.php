<?php

/*
 * Copyright (c) 2018 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\MultiFileUploadBundle\ContaoManager;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use Contao\ManagerPlugin\Config\ContainerBuilder;
use Contao\ManagerPlugin\Config\ExtensionPluginInterface;
use HeimrichHannot\MultiFileUploadBundle\HeimrichHannotContaoMultiFileUploadBundle;
use HeimrichHannot\UtilsBundle\Container\ContainerUtil;

class Plugin implements BundlePluginInterface, ExtensionPluginInterface
{
    public function getBundles(ParserInterface $parser)
    {
        $bundles = [
            BundleConfig::create(HeimrichHannotContaoMultiFileUploadBundle::class)->setLoadAfter([ContaoCoreBundle::class]),
        ];

        if (file_exists($GLOBALS['kernel']->getProjectDir().'/vendor/heimrichhannot/contao-multifileupload/classes/MultiFileUpload.php')) {
            $bundles = [
                BundleConfig::create(HeimrichHannotContaoMultiFileUploadBundle::class)->setLoadAfter([ContaoCoreBundle::class, 'multifileupload']),
            ];
        }

        return $bundles;
    }

    /**
     * Allows a plugin to override extension configuration.
     *
     * @param string           $extensionName
     * @param array            $extensionConfigs
     * @param ContainerBuilder $container
     *
     * @return
     */
    public function getExtensionConfig($extensionName, array $extensionConfigs, ContainerBuilder $container)
    {
        if (in_array('HeimrichHannot\EncoreBundle\HeimrichHannotContaoEncoreBundle', $container->getParameter('kernel.bundles'), true)) {
            return ContainerUtil::mergeConfigFile('huh_encore', $extensionName, $extensionConfigs, $container->getParameter('kernel.project_dir').'/vendor/heimrichhannot/contao-multifileupload-bundle/src/Resources/config/config_encore.yml');
        }
    }
}
