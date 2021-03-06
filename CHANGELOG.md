# Changelog
All notable changes to this project will be documented in this file.

## [1.3.4] - 2021-07-12
- fixed fileupload when files were not in target directory anymore but still saved in database

## [1.3.3] - 2021-03-24
- set maximum dropzone version to 5.7.2, as higher versions currently no compatible

## [1.3.2] - 2020-09-09
- updated js dependencies

## [1.3.1] - 2020-09-09
- fixed token error in contao 4.9

## [1.3.0] - 2020-07-15
- add assets only where widget is included (also encore entry is added automatically, no need to add it manually anymore)
- increased minimum encore version support to 1.5
- moved parameters to own parameters.yml file

## [1.2.2] - 2020-04-20
- fixed setting activeRecord in executePostActionsHook

## [1.2.1] - 2020-01-07
- fixed type hinting issue

## [1.2.0] - 2019-12-19
- refactored bundled asset generation to encore
- dropzone now included in bundle as own asset entry (version 5.5.1)
- removed symfony/framework-bundle, heimrichhannot-contao-components/dropzone-latest and heimrichhannot/contao-components dependencies
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
- `Call to a member function getAdapter() on null in vendor/heimrichhannot/contao-multifileupload-bundle/src/Backend/MultiFileUpload.php (line 79)` (see: #7)
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
- support for [Contao Formhybrid Compatibility Bundle](https://github.com/heimrichhannot/contao-formhybrid-compatibility-bundle)

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

- Server error 500 while trying to warmup cache due to `Uncaught Error: Call to undefined method Contao\\ManagerBundle\\HttpKernel\\ContaoCache::getProjectDir() ` in `Plugin::getBundles`

## [1.0.6] - 2018-09-07

### Fixed

- Server error 500 while trying to warmup cache due to `Uncaught Error: Call to undefined method Contao\\ManagerBundle\\HttpKernel\\ContaoCache::getProjectDir() ` while invoking `config_encore.yml`

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
