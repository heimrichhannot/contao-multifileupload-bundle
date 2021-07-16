<?php

/*
 * Copyright (c) 2020 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\MultiFileUploadBundle\EventListener\Contao;

use Contao\DataContainer;

class GetAttributesFromDcaListener
{
    /**
     * @param array         $attributes
     * @param DataContainer $dc
     *
     * @return array
     *
     * @Hook("getAttributesFromDca")
     */
    public function __invoke(array $attributes, $dc = null): array
    {
        if ('multifileupload' === $attributes['type']) {
            $attributes['id'] = $attributes['name'] = $attributes['strField'] =
                preg_replace('~[^A-Z0-9]+([A-Z0-9]+)(?:[^A-Z0-9]+$)?~i', '_$1', $attributes['id']);
        }

        return $attributes;
    }
}
