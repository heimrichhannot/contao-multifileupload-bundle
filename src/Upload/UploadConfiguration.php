<?php

namespace HeimrichHannot\MultiFileUploadBundle\Upload;

class UploadConfiguration
{
    public int $maxFiles = 10;
    public ?array $extensions = null;
    public ?array $mimeTypes = null;
    public int $minImageWidth = 0;
    public int $minImageHeight = 0;
    public int $maxImageWidth = 0;
    public int $maxImageHeight = 0;
    public ?string $minImageWidthErrorText = null;
    public ?string $minImageHeightErrorText = null;
    public array $validateUploadCallback = [];
}