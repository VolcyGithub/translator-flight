# Contributing to Translator Flight

Thank you for considering contributing to Translator Flight! This document provides guidelines and instructions for contributing to the project.

## Code of Conduct

This project adheres to a code of conduct. By participating, you are expected to uphold this code. Please report unacceptable behavior to svolcy12@gmail.com.

## How to Contribute

### Reporting Bugs

Before creating bug reports, please check the existing issues as you might find that the problem has already been reported. When creating a bug report, please include:

- **Description**: A clear and concise description of the problem
- **Reproduction Steps**: Steps to reproduce the behavior
- **Expected Behavior**: What you expected to happen
- **Actual Behavior**: What actually happened
- **Environment**: PHP version, FlightPHP version, and any relevant dependencies
- **Screenshots/Logs**: If applicable, add screenshots or log output

### Suggesting Enhancements

Enhancement suggestions are welcome! Please provide:

- **Description**: A clear and concise description of the enhancement
- **Motivation**: Why this enhancement would be useful
- **Alternatives**: Any alternative solutions or features you've considered
- **Implementation**: If possible, suggest how the enhancement could be implemented

### Pull Requests

1. **Fork the repository** and create your branch from `main`
2. **Make your changes** following the coding standards below
3. **Add tests** for new functionality or bug fixes
4. **Ensure all tests pass**: `vendor/bin/phpunit`
5. **Update documentation** if your changes affect user-facing functionality
6. **Commit your changes** with clear, descriptive messages
7. **Push to your branch** and create a pull request

## Development Setup

### Prerequisites

- PHP 8.0 or higher
- Composer
- Git
- FlightPHP framework
- volcy/translator-core

### Installation

1. Clone the repository:
```bash
git clone https://github.com/VolcyGithub/translator-flight.git
cd translator-flight
```

2. Install dependencies:
```bash
composer install
```

3. Run tests:
```bash
vendor/bin/phpunit
```

## Coding Standards

### PHP Code Style

- Follow PSR-12 coding standards
- Use 4 spaces for indentation (no tabs)
- Use meaningful variable and function names
- Add PHPDoc comments for all public methods
- Keep methods focused and concise

### Code Organization

- Follow the existing namespace structure
- Place new classes in appropriate directories
- Use interfaces for extensible components
- Keep the bridge framework-specific but well-structured

### Testing

- Write tests for all new functionality
- Maintain test coverage above 80%
- Use descriptive test method names
- Test both positive and negative cases
- Mock external dependencies (FlightPHP, filesystem)

### Documentation

- Update README.md for user-facing changes
- Add PHPDoc comments to public methods
- Keep examples in documentation current

## Commit Messages

Use clear, descriptive commit messages:

```
Add support for FlightPHP 4.0

- Update dependencies to support FlightPHP 4.0
- Update middleware for new FlightPHP API
- Update documentation with version requirements
- Add tests for FlightPHP 4.0 compatibility
```

## Project Structure

```
translator-flight/
├── src/
│   ├── commands/          # Runway CLI commands
│   ├── Middleware/        # Flight middleware
│   ├── TranslatorBootstrap.php
│   └── TrackedBladeOne.php
├── tests/                 # PHPUnit tests
├── .github/              # GitHub-specific files
├── README.md              # Main documentation
├── CONTRIBUTING.md        # This file
├── LICENSE                # MIT License
└── composer.json
```

## Release Process

Releases are managed via Git tags:

1. Update version in composer.json
2. Update CHANGELOG.md
3. Create git tag: `git tag v1.0.0`
4. Push tag: `git push origin v1.0.0`

## Questions?

If you have questions about contributing, feel free to open an issue or contact svolcy12@gmail.com.

## License

By contributing to Translator Flight, you agree that your contributions will be licensed under the MIT License.