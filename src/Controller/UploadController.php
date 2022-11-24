<?php

/*
 * Copyright (c) 2022 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\MultiFileUploadBundle\Controller;

use Contao\Automator;
use Contao\Config;
use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\Dbafs;
use Contao\File;
use Contao\Folder;
use Contao\StringUtil;
use Contao\System;
use Contao\Validator;
use HeimrichHannot\MultiFileUploadBundle\Backend\MultiFileUpload;
use HeimrichHannot\MultiFileUploadBundle\Exception\IllegalFileExtensionException;
use HeimrichHannot\MultiFileUploadBundle\Exception\IllegalMimeTypeException;
use HeimrichHannot\MultiFileUploadBundle\Exception\InvalidImageException;
use HeimrichHannot\MultiFileUploadBundle\Exception\NoUploadException;
use HeimrichHannot\MultiFileUploadBundle\Form\FormMultiFileUpload;
use HeimrichHannot\MultiFileUploadBundle\Response\DropzoneErrorResponse;
use HeimrichHannot\UtilsBundle\File\FileUtil;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class UploadController extends AbstractController
{
    private ContaoCsrfTokenManager $tokenManager;
    private ParameterBagInterface  $parameterBag;
    private FileUtil               $fileUtil;

    public function __construct(ContaoCsrfTokenManager $tokenManager, ParameterBagInterface $parameterBag, FileUtil $fileUtil)
    {
        $this->tokenManager = $tokenManager;
        $this->parameterBag = $parameterBag;
        $this->fileUtil = $fileUtil;
    }

    public function upload(Request $request, string $fieldName, MultiFileUpload $uploader, array $options = []): Response
    {
        $options = array_merge([
            'maxFiles' => 10,
            'extensions' => null,
            'allowedMimeTypes' => null,
            'minImageWidth' => 0,
            'minImageHeight' => 0,
            'maxImageWidth' => 0,
            'maxImageHeight' => 0,
            'minImageWidthErrorText' => null,
            'minImageHeightErrorText' => null,
            'validateUploadCallback' => null,
        ], $options);

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
                (new Automator())->generateSymlinks();
            } catch (\Exception $e) {
                return new DropzoneErrorResponse('There was an error while uploading the file. Please contact the system administrator. Error Code: '.$e->getCode());
            }
        }

        $varFile = $request->files->get($fieldName);

        // Multi-files upload at once
        if (\is_array($varFile)) {
            // prevent disk flooding
            if (\count($varFile) > $options['maxFiles']) {
                return new DropzoneErrorResponse('Bulk file upload violation.');
            }

            /*
             * @var UploadedFile
             */
            foreach ($varFile as $strKey => $objFile) {
                $arrFile = $this->uploadFile($objFile, $uploader, $tempUploadFolder->path, $options);
                $varReturn[] = $arrFile;

                if (isset($varReturn['uuid']) && Validator::isUuid($arrFile['uuid'])) {
                    $uuids[] = $arrFile['uuid'];
                }
            }
        } else {
            // Single-file upload
            $varReturn = $this->uploadFile($varFile, $uploader, $tempUploadFolder->path, $options);

            if (isset($varReturn['uuid']) && Validator::isUuid($varReturn['uuid'])) {
                $uuids[] = $varReturn['uuid'];
            }
        }

        if (null !== $varReturn) {
            $this->varValue = $uuids;
            $response = new JsonResponse([
                'result' => ['data' => $varReturn],
            ]);

            return $response;
        }

        return new Response();
    }

    /**
     * Upload a file, store to $uploadFile and create database entry.
     *
     * @param UploadedFile $uploadedFile UploadedFile        The uploaded file object
     * @param string       $uploadFolder The upload target folder within contao files folder
     *
     * @return array Returns array with file information on success. Returns false if no valid file, file cannot be moved or destination lies outside the
     *               contao upload directory.
     */
    private function uploadFile(UploadedFile $uploadedFile, MultiFileUpload $uploader, string $uploadFolder, array $options)
    {
        $originalFileName = rawurldecode($uploadedFile->getClientOriginalName());
        $originalFileNameEncoded = rawurlencode($originalFileName);
        $sanitizeFileName = $this->fileUtil->sanitizeFileName($uploadedFile->getClientOriginalName());

        if ($uploadedFile->getError()) {
            return $this->prepareErrorArray($uploadedFile->getError(), $originalFileNameEncoded, $sanitizeFileName);
        }

        $error = false;

        try {
            $this->validateExtension($uploadedFile, $options['extensions']);
            $this->validateMimeType($uploadedFile, $options['allowedMimeTypes']);
            $this->validateImageSize($uploadedFile, $options);
        } catch (IllegalFileExtensionException | IllegalMimeTypeException | InvalidImageException $e) {
            return $this->prepareErrorArray($e->getMessage(), $originalFileNameEncoded, $sanitizeFileName);
        }

        $targetFileName = $this->fileUtil->addUniqueIdToFilename($sanitizeFileName, FormMultiFileUpload::UNIQID_PREFIX);

        try {
            $uploadedFile = $uploadedFile->move(TL_ROOT.\DIRECTORY_SEPARATOR.$uploadFolder, $targetFileName);
        } catch (FileException $e) {
            return $this->prepareErrorArray(sprintf($GLOBALS['TL_LANG']['ERR']['moveUploadFile'], $e->getMessage()), $originalFileNameEncoded, $sanitizeFileName);
        }

        $relativePath = ltrim(str_replace(
            $this->parameterBag->get('kernel.project_dir'),
            '',
            $uploadedFile->getPathname()
        ), \DIRECTORY_SEPARATOR);

        $file = null;
        $fileModel = null;

        try {
            // add db record

            $file = new File($relativePath);
            $fileModel = $file->getModel();

            // Update the database
            if (!$fileModel && Dbafs::shouldBeSynchronized($relativePath)) {
                $fileModel = Dbafs::addResource($relativePath);
            }

            if (null === $fileModel) {
                // remove file from file system
                @unlink($this->parameterBag->get('kernel.project_dir').\DIRECTORY_SEPARATOR.$relativePath);

                return $this->prepareErrorArray($GLOBALS['TL_LANG']['ERR']['outsideUploadDirectory'], $originalFileNameEncoded, $sanitizeFileName);
            }

            $strUuid = $fileModel->uuid;
        } catch (\InvalidArgumentException $e) {
            // remove file from file system
            @unlink($this->parameterBag->get('kernel.project_dir').\DIRECTORY_SEPARATOR.$relativePath);

            return $this->prepareErrorArray($GLOBALS['TL_LANG']['ERR']['outsideUploadDirectory'], $originalFileNameEncoded, $sanitizeFileName);
        }

        // validateUploadCallback
        if (\is_array($options['validateUploadCallback'])) {
            foreach ($options['validateUploadCallback'] as $callback) {
                if (!isset($callback[0]) || !class_exists($callback[0])) {
                    continue;
                }

                $objCallback = System::importStatic($callback[0]);

                if (!isset($callback[1]) || !method_exists($objCallback, $callback[1])) {
                    continue;
                }

                if ($errorCallback = $objCallback->{$callback[1]}($file, $this)) {
                    $error = $errorCallback;

                    break; // stop validation on first error
                }
            }
        }

        $arrData = [
            'filename' => $targetFileName,
            'filenameOrigEncoded' => $originalFileNameEncoded,
            'filenameSanitized' => $sanitizeFileName,
        ];

        if (false === $error && false !== ($arrInfo = $uploader->prepareFile($strUuid))) {
            $arrData = array_merge($arrData, $arrInfo);

            return $arrData;
        }

        $arrData['error'] = $error;

        // remove invalid files from tmp folder
        if ($file instanceof File) {
            $file->delete();
        }

        return $arrData;
    }

    private function prepareErrorArray(string $error, string $originalFileNameEncoded, string $sanitizeFileName): array
    {
        return [
            'error' => $error,
            'filenameOrigEncoded' => $originalFileNameEncoded,
            'filenameSanitized' => $sanitizeFileName,
        ];
    }

    private function validateExtension(UploadedFile $objUploadFile, string $extensions = null): void
    {
        $strAllowed = $extensions ?? Config::get('uploadTypes');

        $arrAllowed = StringUtil::trimsplit(',', $strAllowed);

        $strExtension = strtolower($objUploadFile->getClientOriginalExtension());

        if (!$strExtension || !\is_array($arrAllowed) || !\in_array($strExtension, $arrAllowed)) {
            throw new IllegalFileExtensionException(sprintf($GLOBALS['TL_LANG']['ERR']['illegalFileExtension'], $strExtension));
        }
    }

    private function validateMimeType(UploadedFile $uploadedFile, string $mimeTypes = null): void
    {
        $allowedMimeTypes = null;

        if ($mimeTypes) {
            $allowedMimeTypes = StringUtil::trimsplit(',', $mimeTypes);
        }

        if ($allowedMimeTypes) {
            if (empty($allowedMimeTypes)) {
                return;
            }

            if (\in_array($uploadedFile->getMimeType(), $allowedMimeTypes, true)) {
                return;
            }

            throw new IllegalMimeTypeException(sprintf($GLOBALS['TL_LANG']['ERR']['illegalMimeType'], $uploadedFile->getMimeType()));
        }

        if ($uploadedFile->getMimeType() !== $uploadedFile->getClientMimeType()) {
            // allow safe mime types
            switch ($uploadedFile->getMimeType()) {
                // swf might be detected as `application/x-shockwave-flash` instead of `application/vnd.adobe.flash.movie`
                case 'application/x-shockwave-flash':
                    // css files might be detected as the following instead of 'text/css'
                case 'text/x-asm':
                    // csv files might be detected as the following instead of 'text/csv'
                case 'text/plain':
                case 'text/csv':
                case 'text/x-csv':
                case 'text/comma-separated-values':
                case 'text/x-comma-separated-values':
                case 'text/tab-separated-values':
                    return;
            }

            throw new IllegalMimeTypeException(sprintf($GLOBALS['TL_LANG']['ERR']['illegalMimeType'], $uploadedFile->getMimeType()));
        }
    }

    private function validateImageSize(UploadedFile $uploadedFile, array $options): void
    {
        $options = (object) $options;

        $minWidth = $options->minImageWidth ?? 0;
        $minHeight = $options->minImageHeight ?? 0;
        $maxWidth = $options->maxImageWidth ?? Config::get('imageWidth') ?? 0;
        $maxHeight = $options->maxImageHeight ?? Config::get('imageHeight') ?? 0;

        if ($imageSize = @getimagesize($uploadedFile->getPathname())) {
            if ($minWidth > 0 && $imageSize[0] < $minWidth) {
                throw new InvalidImageException(sprintf($options->minImageWidthErrorText ?: $GLOBALS['TL_LANG']['ERR']['minWidth'], $minWidth, $imageSize[0]));
            }

            if ($minHeight > 0 && $imageSize[1] < $minHeight) {
                throw new InvalidImageException(sprintf($options->minImageHeightErrorText ?: $GLOBALS['TL_LANG']['ERR']['minHeight'], $minHeight, $imageSize[1]));
            }

            // Image exceeds maximum image width
            if ($maxWidth > 0 && $imageSize[0] > $maxWidth) {
                throw new InvalidImageException(sprintf($GLOBALS['TL_LANG']['ERR']['filewidth'], $uploadedFile->getClientOriginalName(), $maxWidth));
            }

            // Image exceeds maximum image height
            if ($maxHeight > 0 && $imageSize[1] > $maxHeight) {
                throw new InvalidImageException(sprintf($GLOBALS['TL_LANG']['ERR']['fileheight'], $uploadedFile->getClientOriginalName(), $maxHeight));
            }
        }
    }
}
