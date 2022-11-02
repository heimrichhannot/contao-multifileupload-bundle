<?php

/*
 * Copyright (c) 2022 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\MultiFileUploadBundle\EventListener\Callback;

use Contao\DataContainer;
use Contao\FrontendUser;
use Contao\StringUtil;
use Contao\System;
use HeimrichHannot\MultiFileUploadBundle\Backend\MultiFileUpload;
use HeimrichHannot\MultiFileUploadBundle\File\FilesHandler;
use HeimrichHannot\MultiFileUploadBundle\Form\FormMultiFileUpload;
use HeimrichHannot\UtilsBundle\File\FileUtil;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class OnSubmitCallbackListener
{
    private RequestStack          $requestStack;
    private FileUtil              $fileUtil;
    private ParameterBagInterface $parameterBag;
    private FilesHandler          $filesHandler;

    public function __construct(RequestStack $requestStack, FileUtil $fileUtil, ParameterBagInterface $parameterBag, FilesHandler $filesHandler)
    {
        $this->requestStack = $requestStack;
        $this->fileUtil = $fileUtil;
        $this->parameterBag = $parameterBag;
        $this->filesHandler = $filesHandler;
    }

    /**
     * @param DataContainer|FrontendUser $dc
     */
    public function moveFiles($dc): void
    {
        if (!$dc || !$dc->table) {
            return;
        }

        $arrPost = $this->requestStack->getCurrentRequest()->request->all();

        foreach ($arrPost as $key => $value) {
            if (!isset($GLOBALS['TL_DCA'][$dc->table]['fields'][$key])) {
                continue;
            }

            $arrData = $GLOBALS['TL_DCA'][$dc->table]['fields'][$key];

            if (MultiFileUpload::NAME !== $arrData['inputType']) {
                continue;
            }

            $arrFiles = StringUtil::deserialize($dc->activeRecord->{$key});

            $strUploadFolder = $this->fileUtil->getFolderFromDca($arrData['eval']['uploadFolder'], $dc);

            if (null === $strUploadFolder) {
                throw new \Exception(sprintf($GLOBALS['TL_LANG']['ERR']['uploadNoUploadFolderDeclared'], $key, $this->parameterBag->get('huh.multifileupload.upload_tmp')));
            }

            if (!\is_array($arrFiles)) {
                $arrFiles = [$arrFiles];
            }

            $options = [
                'uniqueIdPrefix' => FormMultiFileUpload::UNIQID_PREFIX,
            ];

            if (isset($arrData['uploadPathCallback']) && \is_array($arrData['uploadPathCallback'])) {
                $options['uploadPathCallback'] = function ($file, $target) use ($arrData, $dc) {
                    foreach ($arrData['uploadPathCallback'] as $callback) {
                        $target = System::importStatic($callback[0])->{$callback[1]}($target, $file, $dc) ?: $target;
                    }

                    return $target;
                };
            }

            $this->filesHandler->moveUploads($arrFiles, $strUploadFolder, $options);
        }
    }
}
