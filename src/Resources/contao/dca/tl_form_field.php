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

$dca['palettes']['multifileupload'] = '{type_legend},type,name,label;{fconfig_legend},mandatory,extensions,maxlength;{store_legend:hide},uploadFolder;{expert_legend:hide},class,accesskey,tabindex,fSize;{template_legend:hide},customTpl;{invisible_legend:hide},invisible';
