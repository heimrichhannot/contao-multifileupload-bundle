<?php

/*
 * Copyright (c) 2022 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\MultiFileUploadBundle\ContaoManager;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use Contao\ManagerPlugin\Config\ConfigPluginInterface;
use Contao\ManagerPlugin\Routing\RoutingPluginInterface;
use HeimrichHannot\MultiFileUploadBundle\HeimrichHannotContaoMultiFileUploadBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Loader\LoaderResolverInterface;
use Symfony\Component\HttpKernel\KernelInterface;

class Plugin implements BundlePluginInterface, ConfigPluginInterface, RoutingPluginInterface
{
    public function getBundles(ParserInterface $parser)
    {
        $loadAfter = [ContaoCoreBundle::class];

        if (file_exists(__DIR__.'/../../../contao-multifileupload/classes/MultiFileUpload.php')) {
            $loadAfter[] = 'multifileupload';
        }

        $bundles = [
            BundleConfig::create(HeimrichHannotContaoMultiFileUploadBundle::class)->setLoadAfter($loadAfter),
        ];

        return $bundles;
    }

    /**
     * {@inheritdoc}
     */
    public function registerContainerConfiguration(LoaderInterface $loader, array $managerConfig)
    {
        $loader->load('@HeimrichHannotContaoMultiFileUploadBundle/Resources/config/services.yml');
        $loader->load('@HeimrichHannotContaoMultiFileUploadBundle/Resources/config/parameters.yml');
    }

    public function getRouteCollection(LoaderResolverInterface $resolver, KernelInterface $kernel)
    {
        return $resolver->resolve(__DIR__.'/../Resources/config/routing.yml')->load(__DIR__.'/../Resources/config/routing.yml');
    }
}
