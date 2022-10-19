<?php

/*
 * Copyright (c) 2022 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\MultiFileUploadBundle\EventListener\Contao;

use Contao\CoreBundle\ServiceAnnotation\Hook;

/**
 * @Hook("getAttributesFromDca")
 */
class GetAttributesFromDcaListener
{
    public function __invoke(array $attributes, $context = null): array
    {
        if ('multifileupload' === $attributes['type']) {
            $attributes['id'] = $attributes['name'] = $attributes['strField'] =
                preg_replace('~[^A-Z0-9]+([A-Z0-9]+)(?:[^A-Z0-9]+$)?~i', '_$1', $attributes['id']);
        }

        return $attributes;
    }
}
