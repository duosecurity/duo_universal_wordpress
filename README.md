# Overview

[![Issues](https://img.shields.io/github/issues/duosecurity/duo_universal_wordpress)](https://github.com/duosecurity/duo_universal_wordpress/issues)
[![Forks](https://img.shields.io/github/forks/duosecurity/duo_universal_wordpress)](https://github.com/duosecurity/duo_universal_wordpress/network/members)
[![Stars](https://img.shields.io/github/stars/duosecurity/duo_universal_wordpress)](https://github.com/duosecurity/duo_univerasl_wordpress/stargazers)
[![License](https://img.shields.io/badge/License-View%20License-orange)](https://github.com/duosecurity/duo_universal_wordpress/blob/master/LICENSE)
License: Apache-2.0

**duo_universal_wordpress** - Duo universal authentication for WordPress logins

This WordPress plugin allows you to quickly add Duo Universal authentication to any WordPress login.

# Usage

Documentation: <http://www.duosecurity.com/docs/universal_wordpress>

### TLS 1.2 and 1.3 Support

duo_universal_wordpress uses PHP's cURL extension and OpenSSL for TLS operations.

TLS support will depend on the versions of multiple libraries:
TLS 1.2 support requires PHP 5.5 or higher, curl 7.34.0 or higher, and OpenSSL 1.0.1 or higher.
TLS 1.3 support requires PHP 7.3 or higher, curl 7.61.0 or higher, and OpenSSL 1.1.1 or higher.

# Tests

Make sure you have composer installed locally.
You could run test with following command:

```
composer install
./vendor/bin/phpunit --process-isolation tests
```

# Support

Report any bugs, feature requests, etc. to us directly:
support@duosecurity.com

Have fun!

<http://www.duosecurity.com>
