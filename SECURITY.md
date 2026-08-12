# Security Policy

## Supported Versions

Currently, only the latest version of Translator Flight is supported with security updates.

| Version | Supported          |
| ------- | ------------------ |
| 1.x.x   | :white_check_mark: Yes |

## Reporting a Vulnerability

If you discover a security vulnerability in Translator Flight, please report it responsibly.

**Do not** report security vulnerabilities through public GitHub issues.

Instead, please send an email to: svolcy12@gmail.com

Please include:
- A description of the vulnerability
- Steps to reproduce the issue
- Any potential impact or exploit details
- If possible, a suggested fix or mitigation

### What to Expect

- You will receive an acknowledgment of your report within 48 hours
- We will provide a timeline for addressing the vulnerability
- We will coordinate with you on the disclosure timeline
- Once fixed, we will release a security update with CVE assignment if appropriate

### Security Best Practices

When using Translator Flight in production:

1. **API Keys**: Never commit API keys to version control
   - Use environment variables for API credentials
   - Rotate keys regularly
   - Use different keys for development and production

2. **File Permissions**: Ensure proper file permissions
   - Translation index files should not be publicly accessible
   - Restrict write access to translation directories

3. **Dependencies**: Keep dependencies updated
   - Regularly run `composer update`
   - Monitor security advisories for dependencies

4. **HTTPS**: Always use HTTPS for API calls
   - All translation drivers use HTTPS endpoints
   - Ensure your server has proper SSL/TLS configuration

5. **Input Validation**: While the library handles most validation, ensure:
   - User input is properly sanitized before translation
   - Translation results are treated as untrusted content

### Security Features

Translator Flight includes several security features inherited from translator-core:

- **SSL/TLS**: All API calls use encrypted connections
- **Input Sanitization**: HTML content is properly sanitized
- **File System**: Uses abstraction layer for safe file operations
- **No Code Execution**: Translation results are never executed as code

## Security Audits

If you're interested in conducting a security audit of Translator Flight, please contact us first at svolcy12@gmail.com to coordinate.

## Acknowledgments

We thank all security researchers who help keep Translator Flight safe by reporting vulnerabilities responsibly.