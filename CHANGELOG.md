# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Updated to support translator-core v1.0.0 with new ID strategy system
- IdStrategy configuration support in TranslatorBootstrap
- Enhanced README with comprehensive configuration examples
- LICENSE file (MIT)
- CONTRIBUTING guidelines
- PHPDoc comments for all public methods

### Changed
- Updated to use stable translator-core ^1.0 dependency
- Changed minimum stability from dev to stable
- Enhanced composer.json with homepage, support links, and contact info
- Updated documentation for ID strategy configuration

### Fixed
- Fixed BladeDriver instantiation to include required IdStrategy parameter
- Improved compatibility with latest translator-core changes

## [1.0.0] - 2026-08-12

### Added
- Initial release of translator-flight
- FlightPHP bootstrap integration for translator-core
- TrackedBladeOne wrapper with view tracking
- Translation middleware for FlightPHP
- Runway CLI commands (translator:scan, translator:build)
- Configuration support for ID strategies
- Locale resolver with fallback support
- Service registration via Flight container

### Dependencies
- PHP 8.0 or higher
- volcy/translator-core ^1.0
- flightphp/core ^3.0
- eftec/bladeone ^4.0

### Optional Dependencies
- flightphp/runway ^1.0 (for CLI commands)

---

## Versioning

For the versions available, see the [tags on this repository](https://github.com/VolcyGithub/translator-flight/tags).