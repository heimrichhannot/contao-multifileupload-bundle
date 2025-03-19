# Changelog

All notable changes to this project will be documented in this file.

## [1.9.1] - 2025-03-19
- Fixed: scss nesting rules for native css nesting compatibility

## [1.9.0] - 2024-06-06
- Changed: added UploadConfiguration class to avoid type issues with configuration

## [1.8.9] - 2024-05-16
- Fixed: file extensions could be array

## [1.8.8] - 2024-02-09
- Fixed: scss division deprecation

## [1.8.7] - 2024-02-01
- Fixed: exception in form generator when no uploadField is set
- Fixed: setting max files to zero not allowing multiple files in form generator
- Fixed: exception on upload in form generator

## [1.8.6] - 2023-09-21
- Fixed: usage of request bundle

## [1.8.5] - 2023-09-05
- Fixed: make uploadPath field mandatory
- Fixed: accessibility issues

## [1.8.4] - 2023-06-15
- Fixed: invalid request token

## [1.8.3] - 2023-05-15
- Fixed: previews when there are multiple dropzones at once

## [1.8.2] - 2023-02-21
- Fixed: issues if other widgets also do ajax requests in backend

## [1.8.1] - 2022-11-30
- Fixed: initialize dropzone after `ajax_change` window event

## [1.8.0] - 2022-11-25
- Added: notification center compatiblity
- Added: PostUploadEvent
- Fixed: token issues on frontend uploads
- Deprecated: postUpload hook

## [1.7.0] - 2022-11-02
- Added: formgenerator support ([#46])

## [1.6.0] - 2022-10-19
- Changed: refactor hooks to newer contao standards ([#44])
- Changed: raised dependencies for php and contao ([#44])
- Changed: refactor encore bundle integration to encore contracts ([#45]) 

## [1.5.4] - 2022-06-15
- Fixed: values get lost if form has error

## [1.5.3] - 2022-06-07
- Fixed: warnings with php 8

## [1.5.2] - 2022-06-02
- Fixed: warnings in php 8

## [1.5.1] - 2021-11-16
- Changed: enhanced max file size calculation
- Changed: enhanced readme

## [1.5.0] - 2021-10-18
- Added: option to configure allowed mime type ([#31], thanks to [@rabaus])
- Added: support for dropzone timeout option ([#32], thanks to [@rabaus])

## [1.4.1] - 2021-10-13
- Fixed: exception when invalid upload size configuration (now shows warning message instead)

## [1.4.0] - 2021-09-24

- Added: php 8 support
- Fixed: widget value decoding for contao 4.9.18+

## [1.3.6] - 2021-08-25

- Fixed: dca images size attributes not properly evaluated for image size check
- Fixed: missing utils bundle dependency

## [1.3.5] - 2021-08-24

- Fixed: image size check for uploaded images not working

## [1.3.4] - 2021-07-12

- fixed fileupload when files were not in target directory anymore but still saved in database

## [1.3.3] - 2021-03-24

- set maximum dropzone version to 5.7.2, as higher versions currently no compatible

## [1.3.2] - 2020-09-09

- updated js dependencies

## [1.3.1] - 2020-09-09

- fixed token error in contao 4.9

## [1.3.0] - 2020-07-15

- add assets only where widget is included (also encore entry is added automatically, no need to add it manually
  anymore)
- increased minimum encore version support to 1.5
- moved parameters to own parameters.yml file

## [1.2.2] - 2020-04-20

- fixed setting activeRecord in executePostActionsHook

## [1.2.1] - 2020-01-07

- fixed type hinting issue

## [1.2.0] - 2019-12-19

- refactored bundled asset generation to encore
- dropzone now included in bundle as own asset entry (version 5.5.1)
- removed symfony/framework-bundle, heimrichhannot-contao-components/dropzone-latest and
  heimrichhannot/contao-components dependencies
- config files now loaded via Plugin class
- fixed an js excetion when upload field id contains invalid characters
- fixed an typo in composer.json

## [1.1.7] - 2019-03-19

#### Changed

- added an polish translation

## [1.1.6] - 2019-03-14

#### Changed

- updated english translations

## [1.1.5] - 2019-03-05

#### Fixed

- backendtemplate rendered in frontend

## [1.1.4] - 2019-02-14

#### Fixed

- `Call to a member function getAdapter() on null in vendor/heimrichhannot/contao-multifileupload-bundle/src/Backend/MultiFileUpload.php (line 79)` (
  see: #7)
- Typo in `multifileupad.js` (see #6)
- contao 4.6 support (see #4)
- started `FFL` integration with contao form-generator (see #1 and #3)

## [1.1.3] - 2019-01-22

#### Fixed

- error with non working error messages due incompatible error responses
- symlink command if temp folder not symlinked

## [1.1.2] - 2019-01-08

#### Fixed

- access to utilsBundle in JS

## [1.1.1] - 2018-12-14

#### Added

- support
  for [Contao Formhybrid Compatibility Bundle](https://github.com/heimrichhannot/contao-formhybrid-compatibility-bundle)

## [1.1.0] - 2018-12-12

#### Changed

- switched `heimrichhannot/dropzone-latest` for `heimrichhannot-contao-components/dropzone-latest`
- refactored `executePostActionsHook` into `HookListener` class

#### Fixed

- template label output

## [1.0.13] - 2018-12-11

### Fixed

- error in file upload
- php cs fixer config

## [1.0.12] - 2018-12-11

### Fixed

- frontend output

## [1.0.11] - 2018-11-19

### Fixed

- symfony 4.x and contao 4.6+ compatibility

## [1.0.10] - 2018-10-16

### Fixed

- compile error when encore bundle not installed

## [1.0.9] - 2018-10-12

### Fixed

- trigger `__submitOnChange` only if multiple files can be uploaded on removed file

## [1.0.8] - 2018-10-10

### Fixed

- fixed wrong parameter
- fixed error with empty file check
- removed hard encore-bundle dependency

## [1.0.7] - 2018-09-07

### Fixed

- Server error 500 while trying to warmup cache due
  to `Uncaught Error: Call to undefined method Contao\\ManagerBundle\\HttpKernel\\ContaoCache::getProjectDir() `
  in `Plugin::getBundles`

## [1.0.6] - 2018-09-07

### Fixed

- Server error 500 while trying to warmup cache due
  to `Uncaught Error: Call to undefined method Contao\\ManagerBundle\\HttpKernel\\ContaoCache::getProjectDir() ` while
  invoking `config_encore.yml`

## [1.0.5] - 2018-09-05

### Fixed

- MultiFileUpload::loadDcaConfig()

## [1.0.4] - 2018-08-27

### Fixed

- MultiFileUpload::getMaximumUploadSize() System:log call

## [1.0.3] - 2018-08-13

### Fixed

- multiple multifile upload fields

## [1.0.2] - 2018-05-15

### Fixed

- `timeout` configuration parameter support provided to exceed default 30 second timeout

## [1.0.1] - 2018-04-11

### Changed

- enhanced error messages

## [1.0.0] - 2018-04-03

### Added

- initial version

[#46]: https://github.com/heimrichhannot/contao-multifileupload-bundle/pull/46
[#45]: https://github.com/heimrichhannot/contao-multifileupload-bundle/pull/45
[#44]: https://github.com/heimrichhannot/contao-multifileupload-bundle/pull/44
[#32]: https://github.com/heimrichhannot/contao-multifileupload-bundle/pull/32
[#31]: https://github.com/heimrichhannot/contao-multifileupload-bundle/pull/31
[@rabaus]: https://github.com/rabauss
