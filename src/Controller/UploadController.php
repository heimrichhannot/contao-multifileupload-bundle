<?php

/*
 * Copyright (c) 2022 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\MultiFileUploadBundle\Controller;

use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\Folder;
use Contao\System;
use HeimrichHannot\MultiFileUploadBundle\Exception\NoUploadException;
use HeimrichHannot\MultiFileUploadBundle\Response\DropzoneErrorResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class UploadController extends AbstractController
{
    private ContaoCsrfTokenManager $tokenManager;
    private ParameterBagInterface  $parameterBag;

    public function __construct(ContaoCsrfTokenManager $tokenManager, ParameterBagInterface $parameterBag)
    {
        $this->tokenManager = $tokenManager;
        $this->parameterBag = $parameterBag;
    }

    /**
     * @throws NoUploadException
     */
    public function upload(Request $request, string $fieldName): Response
    {
        $uuids = [];
        $varReturn = null;

        if (!$request->request->has('REQUEST_TOKEN')) {
            return new DropzoneErrorResponse('No Request Token!');
        }

        $token = $this->tokenManager->getToken($request->request->get('REQUEST_TOKEN'));

        if (!$this->tokenManager->isTokenValid($token)) {
            return new DropzoneErrorResponse('Invalid Request Token!');
        }

        if (!$request->files->has($fieldName)) {
            throw new NoUploadException();
        }

        $tempUploadFolder = new Folder($this->parameterBag->get('huh.multifileupload.upload_tmp'));

        // tmp directory is not public, mandatory for preview images
        $tmpPath = $this->parameterBag->get('contao.web_dir').\DIRECTORY_SEPARATOR.$this->parameterBag->get('huh.multifileupload.upload_tmp');

        if (!file_exists($tmpPath)
        ) {
            try {
                $tempUploadFolder->unprotect();

                $automator = System::importStatic('Contao\Automator', 'Automator');
                $automator->generateSymlinks();
            } catch (\Exception $e) {
                $response = new DropzoneErrorResponse('There was an error while uploading the file. Please contact the system administrator. Error Code: '.$e->getCode());
            }
        }

        return new Response();
//
//        $strField = $this->name;
//        $varFile = $request->files->get($strField);
//        // Multi-files upload at once
//        if (\is_array($varFile)) {
//            // prevent disk flooding
//            if (\count($varFile) > $this->maxFiles) {
//                $response = new DropzoneErrorResponse();
//                $response->setMessage('Bulk file upload violation.');
//                $response->output();
//            }
//
//            /*
//             * @var UploadedFile
//             */
//            foreach ($varFile as $strKey => $objFile) {
//                $arrFile = $this->uploadFile($objFile, $tempUploadFolder->path);
//                $varReturn[] = $arrFile;
//
//                if (isset($varReturn['uuid']) && Validator::isUuid($arrFile['uuid'])) {
//                    $uuids[] = $arrFile['uuid'];
//                }
//            }
//        } else {
//            // Single-file upload
//            $varReturn = $this->uploadFile($varFile, $tempUploadFolder->path);
//
//            if (isset($varReturn['uuid']) && Validator::isUuid($varReturn['uuid'])) {
//                $uuids[] = $varReturn['uuid'];
//            }
//        }
//
//        if (null !== $varReturn) {
//            $this->varValue = $uuids;
//            $response = new ResponseSuccess();
//            $objResult = new ResponseData();
//            $objResult->setData($varReturn);
//            $response->setResult($objResult);
//
//            return $response;
//        }
    }
}
