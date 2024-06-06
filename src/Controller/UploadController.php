<?php

/*
 * Copyright (c) 2023 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\MultiFileUploadBundle\Controller;

use Ausi\SlugGenerator\SlugGenerator;
use Contao\Automator;
use Contao\Config;
use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\Dbafs;
use Contao\File;
use Contao\Folder;
use Contao\StringUtil;
use Contao\Validator;
use HeimrichHannot\MultiFileUploadBundle\Backend\MultiFileUpload;
use HeimrichHannot\MultiFileUploadBundle\Exception\IllegalFileExtensionException;
use HeimrichHannot\MultiFileUploadBundle\Exception\IllegalMimeTypeException;
use HeimrichHannot\MultiFileUploadBundle\Exception\InvalidImageException;
use HeimrichHannot\MultiFileUploadBundle\Exception\NoUploadException;
use HeimrichHannot\MultiFileUploadBundle\Form\FormMultiFileUpload;
use HeimrichHannot\MultiFileUploadBundle\Response\DropzoneErrorResponse;
use HeimrichHannot\MultiFileUploadBundle\Upload\UploadConfiguration;
use HeimrichHannot\UtilsBundle\Util\Utils;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Csrf\CsrfToken;

class UploadController extends AbstractController
{
    private ContaoCsrfTokenManager $tokenManager;
    private ParameterBagInterface  $parameterBag;
    private Utils $utils;

    public function __construct(ContaoCsrfTokenManager $tokenManager, ParameterBagInterface $parameterBag, Utils $utils)
    {
        $this->tokenManager = $tokenManager;
        $this->parameterBag = $parameterBag;
        $this->utils = $utils;
    }

    /**
     * @internal Not covered by bc promise
     */
    public function upload(Request $request, string $fieldName, MultiFileUpload $uploader, ?UploadConfiguration $configuration = null): Response
    {
        if (!$configuration) {
            $configuration = new UploadConfiguration();
        }

        $uuids = [];
        $varReturn = null;

        if (!$request->request->has('REQUEST_TOKEN')) {
            return new DropzoneErrorResponse('No Request Token!');
        }

        $token = new CsrfToken('contao_csrf_token', $request->request->get('requestToken') ?? $request->request->get('REQUEST_TOKEN'));

        if (!$this->tokenManager->isTokenValid($token)) {
            return new DropzoneErrorResponse('Invalid Request Token!');
        }

        if (!$request->files->has($fieldName)) {
            throw new NoUploadException();
        }

        $tempUploadFolder = new Folder($this->parameterBag->get('huh.multifileupload.upload_tmp'));

        if (!$tempUploadFolder->isUnprotected()) {
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
            if (\count($varFile) > $configuration->maxFiles) {
                return new DropzoneErrorResponse('Bulk file upload violation.');
            }

            /*
             * @var UploadedFile
             */
            foreach ($varFile as $strKey => $objFile) {
                $arrFile = $this->uploadFile($objFile, $uploader, $tempUploadFolder->path, $configuration);
                $varReturn[] = $arrFile;

                if (isset($varReturn['uuid']) && Validator::isUuid($arrFile['uuid'])) {
                    $uuids[] = $arrFile['uuid'];
                }
            }
        } else {
            // Single-file upload
            $varReturn = $this->uploadFile($varFile, $uploader, $tempUploadFolder->path, $configuration);

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
    private function uploadFile(UploadedFile $uploadedFile, MultiFileUpload $uploader, string $uploadFolder, UploadConfiguration $configuration)
    {
        $originalFileName = rawurldecode($uploadedFile->getClientOriginalName());
        $originalFileNameEncoded = rawurlencode($originalFileName);

        $file = new File($originalFileNameEncoded);
        $sanitizeFileName = (new SlugGenerator())->generate($file->name, ['validChars' => 'a-z0-9_']).($file->extension ? '.'.strtolower($file->extension) : ''); ;

        if ($uploadedFile->getError()) {
            return $this->prepareErrorArray($uploadedFile->getError(), $originalFileNameEncoded, $sanitizeFileName);
        }

        $error = false;

        try {
            $this->validateExtension($uploadedFile, $configuration);
            $this->validateMimeType($uploadedFile, $configuration);
            $this->validateImageSize($uploadedFile, $configuration);
        } catch (IllegalFileExtensionException | IllegalMimeTypeException | InvalidImageException $e) {
            return $this->prepareErrorArray($e->getMessage(), $originalFileNameEncoded, $sanitizeFileName);
        }

        $file = new File($sanitizeFileName);
        $targetFileName =  $file->filename.uniqid(FormMultiFileUpload::UNIQID_PREFIX, true).($file->extension ? '.' .$file->extension : '');

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
        foreach ($configuration->validateUploadCallback as $callback) {

            $error = $this->utils->dca()->explodePalette($callback, $file, $this);
            if ($error) {
                // stop validation on first error
                break;
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

    private function validateExtension(UploadedFile $objUploadFile, UploadConfiguration $configuration): void
    {
        if (!$configuration->extensions) {
            $configuration = clone $configuration;
            $strAllowed = Config::get('uploadTypes');
            $configuration->extensions = StringUtil::trimsplit(',', $strAllowed);
        }

        $extension = strtolower($objUploadFile->getClientOriginalExtension());

        if (!\in_array($extension, $configuration->extensions)) {
            throw new IllegalFileExtensionException(sprintf($GLOBALS['TL_LANG']['ERR']['illegalFileExtension'], $extension));
        }
    }

    private function validateMimeType(UploadedFile $uploadedFile, UploadConfiguration $configuration): void
    {
        if ($configuration->mimeTypes) {
            if (empty($configuration->mimeTypes)) {
                return;
            }

            if (\in_array($uploadedFile->getMimeType(), $configuration->mimeTypes, true)) {
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

    private function validateImageSize(UploadedFile $uploadedFile, UploadConfiguration $configuration): void
    {
        $minWidth = $configuration->minImageWidth ?? 0;
        $minHeight = $configuration->minImageHeight ?? 0;
        $maxWidth = $configuration->maxImageWidth ?? 0;
        $maxHeight = $configuration->maxImageHeight ?? 0;

        if ($imageSize = @getimagesize($uploadedFile->getPathname())) {
            if ($minWidth > 0 && $imageSize[0] < $minWidth) {
                throw new InvalidImageException(sprintf($configuration->minImageWidthErrorText ?? $GLOBALS['TL_LANG']['ERR']['minWidth'], $minWidth, $imageSize[0]));
            }

            if ($minHeight > 0 && $imageSize[1] < $minHeight) {
                throw new InvalidImageException(sprintf($configuration->minImageHeightErrorText ?? $GLOBALS['TL_LANG']['ERR']['minHeight'], $minHeight, $imageSize[1]));
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
