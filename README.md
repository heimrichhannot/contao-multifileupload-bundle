
![](https://img.shields.io/packagist/v/heimrichhannot/contao-multifileupload-bundle.svg)
![](https://img.shields.io/packagist/l/heimrichhannot/contao-multifileupload-bundle.svg)
![](https://img.shields.io/packagist/dt/heimrichhannot/contao-multifileupload-bundle.svg)
[![](https://img.shields.io/travis/heimrichhannot/contao-multifileupload-bundle/master.svg)](https://travis-ci.org/heimrichhannot/contao-multifileupload-bundle/)
[![](https://img.shields.io/coveralls/heimrichhannot/contao-multifileupload-bundle/master.svg)](https://coveralls.io/github/heimrichhannot/contao-multifileupload-bundle)

# Contao Multi File Upload Bundle

Contao front end widget that provides [dropzonejs.com](http://www.dropzonejs.com/) functionality to both back and front end.

![alt text](/doc/multifileupload-demo.jpg "Multifileupload demo within contao backend")


## Features

* javascript written in native javascript
* support for jquery ajaxComplete and mootools ajax_change events
* support for [Encore Bundle](https://github.com/heimrichhannot/contao-encore-bundle)
* support for [Formhybrid Compatibility Bundle Bundle](https://github.com/heimrichhannot/contao-formhybrid-compatibility-bundle) formhybrid_ajax_complete event 

## Setup

### Install

1. Install heimrichhannot/contao-multifileupload-bundle via composer or contao manager

    ```
    composer require heimrichhannot/contao-multifileupload-bundle
    ```
   
2. Update your database

### Usage

Create a widget of inputType `multifileupload`. It is usable in the contao backend or in the contao frontend in combination with [Formhybrid](https://github.com/heimrichhannot/contao-formhybrid).

```php
$GLOBALS['TL_DCA']['tl_example']['fields']['example_upload'] = [
    'inputType' => 'multifileupload',
    'eval'      => [
        'extensions'     => string, # A comma-seperated list of allowed file types (e.g. "jpg,png"). Default: 'Config::get('uploadTypes')'
        'fieldType'      => ['radio'|'checkbox'], # Use radio for single file upload, checkbox for multi file upload
        'uploadFolder'   => array|string|callable, # Set the folder where uploaded files are stored after submission. Can be a static string (e.g. 'files/upload') or a callback function.
        'maxFiles'       => int, # Maximum number of files that can be uploaded. Works only if multi file upload is allowed (see fieldType). Default: 10
        'maxUploadSize'  => int|string, # Maximum upload size in byte, KiB ("100K"), MiB ("4M") or GiB ("1G"). Default: minimum from Config::get('maxFileSize') and ini_get('upload_max_filesize')
        'minImageWidth'  => int, # Minimum image width in pixel. Default: 0
        'minImageHeight' => int, # Minimum image height in pixel. Default: 0
        'maxImageWidth'  => int, # Maximum image width in pixel. Default: Config::get('imageWidth')
        'maxImageHeight' => int, # Maximum image height in pixel. Default: Config::get('imageHeight')
        'labels'         => [ # Optional. Custom text that will be placed in the dropzone field. Typically a reference to the global language array.
            'head' => string,
            'body' => string ,
        ],
        'skipDeleteAfterSubmit' => boolean, # Prevent file removal from filesystem. Default false
    ],
    'uploadPathCallback' => [[MyUploadCallback::class, 'onUploadPathCallback']],
    'validateUploadCallback' => [[MyUploadCallback::class, 'onValidateUploadCallback']],
    'sql'       => "blob NULL",
];
```

Example for simple single image file upload:

```php
$GLOBALS['TL_DCA']['tl_example']['fields']['example_upload'] = [
    'inputType' => 'multifileupload',
    'eval'      => [
        'tl_class'      => 'clr',
        'extensions'    => Config::get('validImageTypes'),
        'fieldType'     => 'radio',
        'uploadFolder'        => 'files/uploads'
    ],
    'sql'       => "blob NULL",
];
```

Example for simple multiple image file upload:

```php
$GLOBALS['TL_DCA']['tl_example']['fields']['example_upload'] = [
    'inputType' => 'multifileupload',
    'eval'      => [
        'tl_class'       => 'clr',
        'extensions'     => Config::get('validImageTypes'),
        'fieldType'      => 'checkbox',
        'uploadFolder'   => 'files/uploads'
    ],
    'sql'       => "blob NULL",
];
```

Example for multi image upload with additional config (maximum 5 files with custom image size):

```php
$GLOBALS['TL_DCA']['tl_example']['fields']['example_upload'] = [
    'inputType' => 'multifileupload',
    'eval'      => [
        'tl_class'       => 'clr',
        'extensions'     => Config::get('validImageTypes'),
        'fieldType'      => 'checkbox',
        'maxFiles'       => 5,
        'minImageWidth'  => 600,
        'minImageHeight' => 300,
        'maxImageWidth'  => 1600,
        'maxImageHeight' => 1200,
        'uploadFolder'   => 'files/uploads'
    ],
    'sql'       => "blob NULL",
];
```

## Documentation

### Supported dropzone config options

The bundles support most dropzone config options. Just pass them as eval attribute. See [Dropzone Documentation](https://docs.dropzone.dev/configuration/basics/configuration-options) for more information. Some additional node:

* `addRemoveLinks` (boolean, default true): If true, this will add a link to every file preview to remove or cancel (if already uploading) the file.
* `maxFilesize`: Is set by `maxUploadSize` eval property 

### Flow chart

A flowchart with description of the full upload procedure with callback injection can be found here: [Flowchart](http://htmlpreview.github.io/?https://github.com/heimrichhannot/contao-multifileupload-bundle/blob/master/doc/upload-flow-chart.html).

### Additional eval properties

Additional properties can be set in your fields eval section.

Name          | Default    | Description
------------- | ---------- | -----------
minImageWidthErrorText | $GLOBALS['TL_LANG']['ERR']['minWidth'] | Custom error message for minimum image width. (arguments provided: 1 - minimum width from config, 2 - current image width)
minImageHeightErrorText | $GLOBALS['TL_LANG']['ERR']['minHeight'] | Custom error message for minimum image height. (arguments provided: 1 - minimum height from config, 2 - current image height)
maxImageWidthErrorText | $GLOBALS['TL_LANG']['ERR']['maxWidth'] | Custom error message for maximum image width. (arguments provided: 1 - maximum width from config, 2 - current image width)
maxImageHeightErrorText | $GLOBALS['TL_LANG']['ERR']['maxHeight'] | Custom error message for maximum image height. (arguments provided: 1 - maximum height from config, 2 - current image height)
createImageThumbnails | boolean(true) | Set to false if you dont want to preview thumbnails.
mimeFolder | system/modules/multifileupload/assets/img/mimetypes/Numix-uTouch | The relative path from contao root to custom mimetype folder, mimetypes.json and images must lie inside. (example: system/modules/multifileupload/assets/img/mimetypes/Numix-uTouch)
mimeThumbnailsOnly | boolean(false) | Set to true if you want to show mime image thumbnails only, and no image preview at all. (performance improvement)
thumbnailWidth | 90 | The thumbnail width (in px) of the uploaded file preview within the dropzone preview container.
thumbnailHeight | 90 | The thumbnail height (in px) of the uploaded file preview within the dropzone preview container.
hideLabel | false | Hide widget label (Frontend)
mimeTypes | `null` | A comma separated list of allowed mime types (e.g. `'application/x-compressed,application/x-zip-compressed,application/zip,multipart/x-zip'`). Set to empty string `''` if you don't want to restrict mime types. Set to `null` if you just want to restrict mime types if they differ while automatic detection.
timeout | null| Dropzone Request timeout in milliseconds. See [Documentation](https://docs.dropzone.dev/configuration/basics/configuration-options)


### Field Callbacks

Type | Arguments | Expected return value | Description
---- | ---- | ---- | -----------
uploadPathCallback | $strTarget, \File $objFile, \DataContainer $dc | $strTarget | Manipulate the upload path after form submission (run within onsubmit_callback).
validateUploadCallback | \File $objFile, \Widget $objWidget | boolean(false) or string with frontend error message | Validate the uploaded file and add an error message if file does not pass validation, otherwise boolean(false) is expected.

