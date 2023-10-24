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

**unittest**

Make sure you have composer installed locally.
You could run test with following command:

```
composer install
./vendor/bin/phpunit --process-isolation tests
```

**integration test**
To test this plugin with running instance of workpress.
Use the provided docker compose file
```
composer install
docker compose up
```
Once docker compose running, visit localhost to finish set wordpress.

After wordpress setted up, navigate to plugin page from the left side menu.

Active Duo Universal.

Click on setting, then copy paste required ikey, skey, api_host from your duo integration page.

Upon save changes, you will should see Duo Prompt for 2fa.

# Support

Report any bugs, feature requests, etc. to us directly:
support@duosecurity.com

Have fun!

<http://www.duosecurity.com>
