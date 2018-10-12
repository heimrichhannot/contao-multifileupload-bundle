# Changelog
All notable changes to this project will be documented in this file.

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
