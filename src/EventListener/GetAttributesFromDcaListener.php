<?php
/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2019 Heimrich & Hannot GmbH
 *
 * @author  Thomas Körner <t.koerner@heimrich-hannot.de>
 * @license http://www.gnu.org/licences/lgpl-3.0.html LGPL
 */


namespace HeimrichHannot\MultiFileUploadBundle\EventListener;


use Contao\DataContainer;

class GetAttributesFromDcaListener
{
    /**
     * @Hook("getAttributesFromDca")
     */
    public function onGetAttributesFromDca(array $attributes, DataContainer $dc = null): array
    {
        if ('multifileupload' === $attributes['type']) {
            $attributes['id'] = $attributes['name'] = $attributes['strField'] =
                preg_replace('~[^A-Z0-9]+([A-Z0-9]+)(?:[^A-Z0-9]+$)?~i', '_$1', $attributes['id']);
        }
        return $attributes;
    }
}