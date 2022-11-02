<?php

/*
 * Copyright (c) 2022 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

$dca = &$GLOBALS['TL_DCA']['tl_form_field'];

/*
 * Palettes
 */

$dca['palettes']['multifileupload'] =
    '{type_legend},type,name,label;
    {fconfig_legend},mandatory,extensions,mf_maxFileSize,mf_maxFiles;
    {store_legend},uploadFolder;
    {expert_legend:hide},class,accesskey,tabindex,fSize;
    {template_legend:hide},customTpl;
    {invisible_legend:hide},invisible';

$dca['fields']['mf_maxFiles'] = [
    'exclude' => true,
    'inputType' => 'text',
    'eval' => ['rgxp' => 'natural', 'tl_class' => 'w50'],
    'sql' => 'smallint(5) unsigned NOT NULL default 0',
];

$dca['fields']['mf_maxFileSize'] = [
    'exclude' => true,
    'inputType' => 'text',
    'eval' => ['rgxp' => 'natural', 'tl_class' => 'w50'],
    'sql' => 'int(10) unsigned NOT NULL default 0',
];
