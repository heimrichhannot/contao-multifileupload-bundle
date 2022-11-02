<?php

/*
 * Copyright (c) 2022 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\MultiFileUploadBundle\File;

use Contao\Dbafs;
use Contao\File;
use Contao\FilesModel;
use Contao\System;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class FilesHandler
{
    private ParameterBagInterface $parameterBag;

    public function __construct(ParameterBagInterface $parameterBag)
    {
        $this->parameterBag = $parameterBag;
    }

    /**
     * @param array  $files List of uuids
     * @param string $path  Relative path from project root
     *
     * @throws \Exception
     *
     * @internal Not covered by bc promise
     */
    public function moveUploads(array $files, string $targetPath, array $options = []): void
    {
        $options = array_merge([
            'uploadPathCallback' => null,
            'uniqueIdPrefix' => '-',
        ], $options);

        $files = FilesModel::findMultipleByUuids($files);

        if (!$files) {
            return;
        }

        $paths = $files->fetchEach('path');

        $targets = [];

        foreach ($paths as $path) {
            $file = new File($path);
            $target = $targetPath.'/'.$file->name;

            if (\is_callable($options['uploadPathCallback'])) {
                $target = $options['uploadPathCallback']($file, $target);
            }

            if (str_starts_with($file->path, ltrim($target, '/'))) {
                continue;
            }

            while (file_exists($this->parameterBag->get('kernel.project_dir').\DIRECTORY_SEPARATOR.$target)) {
                $target = $targetPath.\DIRECTORY_SEPARATOR.$file->filename.uniqid($options['uniqueIdPrefix'].$file->extension);
            }

            $storedFile = FilesModel::findByPath($target);

            if (null !== $storedFile) {
                $storedFile->delete();
            }

            if ($file->renameTo($target)) {
                $targets[] = $target;
                $fileModel = $file->getModel();

                if (!$fileModel && Dbafs::shouldBeSynchronized($target)) {
                    Dbafs::addResource($target);
                }

                continue;
            }

            $targets[] = $path;
        }

        // HOOK: post upload callback
        if (isset($GLOBALS['TL_HOOKS']['postUpload']) && \is_array($GLOBALS['TL_HOOKS']['postUpload'])) {
            foreach ($GLOBALS['TL_HOOKS']['postUpload'] as $callback) {
                if (\is_array($callback)) {
                    System::importStatic($callback[0])->{$callback[1]}($targets);
                } elseif (\is_callable($callback)) {
                    $callback($targets);
                }
            }
        }
    }
}
