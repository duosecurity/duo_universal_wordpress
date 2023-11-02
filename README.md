# Overview

[![Issues](https://img.shields.io/github/issues/duosecurity/duo_universal_wordpress)](https://github.com/duosecurity/duo_universal_wordpress/issues)
[![Forks](https://img.shields.io/github/forks/duosecurity/duo_universal_wordpress)](https://github.com/duosecurity/duo_universal_wordpress/network/members)
[![Stars](https://img.shields.io/github/stars/duosecurity/duo_universal_wordpress)](https://github.com/duosecurity/duo_univerasl_wordpress/stargazers)
[![License](https://img.shields.io/badge/License-View%20License-orange)](https://github.com/duosecurity/duo_universal_wordpress/blob/master/LICENSE)
License: Apache-2.0

**duo_universal_wordpress** - Duo universal authentication for WordPress logins

This WordPress plugin allows you to quickly add Duo Universal authentication to any WordPress login.

This plugin reaches out to Duo's MFA cloud service for the configured user roles which provides an additional layer of authentication.
For more information about Duo's privacy policy see https://duo.com/legal/cisco-online-privacy-statement
Duo's terms of service can be found here https://duo.com/legal/terms

# Usage

Documentation: <http://www.duosecurity.com/docs/universal_wordpress>

### TLS 1.2 and 1.3 Support

duo_universal_wordpress uses PHP's cURL extension and OpenSSL for TLS operations.

TLS support will depend on the versions of multiple libraries:
TLS 1.2 support requires PHP 5.5 or higher, curl 7.34.0 or higher, and OpenSSL 1.0.1 or higher.
TLS 1.3 support requires PHP 7.3 or higher, curl 7.61.0 or higher, and OpenSSL 1.1.1 or higher.

# Development
### Getting started
`php` and `composer` are necessary for local development. Using `brew`:
```
brew install php
brew install composer
```

Additionally, on Macs using Apple Silicon (M1, M2, etc.), `gd` is needed:
```
brew install gd
```

Then install project dependencies:
```
composer install
```

### Dev Environment
A docker container is included as a development environment. To use it, run:
```
docker-compose up -d
```

Once created, navigate to `localhost/wp-login.php` in a browser. Install
Wordpress and create an admin account. Once installed, you can find the 
installation at `/var/www/html/`. Your duo_universal_wordpress repo will be 
mirrored to `/var/www/html/wp-content/plugins/duo_universal_wordpress` so the 
plugin is automatically installed.

To activate the plugin, login to Wordpress and go to the Plugins page. Activate
Duo Universal, and then click Settings for the plugin. Enter the ikey, skey, 
and api_host from your Duo Wordpress integration. Upon save, you will see the 
Duo prompt for 2FA.

#### Docker environment variables
The following may be set in a `.env` file to be applied to the dev docker 
container:

* `HTTP_PORT`: HTTP port to use. Default: `80`
* `HTTPS_PORT`: HTTPS port to use. Default `443`
* `DB_PLATFORM`: The platform to use for the `db` service defined in the docker 
compose file. Defaults to the host machine's platform. The image used for the
`db` service does not have `linux/arm64/v8` support. On such hosts (e.g. Apple
Silicon Macs), set this to `linux/amd64`.
* `WORDPRESS_VERSION`: The WordPress version to use when building the docker 
container. Must be a valid tag per https://hub.docker.com/_/wordpress/tags. 
Defaults to `latest`. Note that only one container at a time is currently 
supported, so when switching versions you'll need to remove the existing
dev container before running `docker-compose up -d` again. 

#### Wordpress CLI
The wordpress installation includes the `wp` command line tool. Note that all
commands must be run with `--allow-root` when shelled into the container as
`root`.

Some helpful commands:

* `wp core multisite-convert`: converts the installation to multi-site. Note
that there is not a good way to undo this.
* `wp plugin deactivate duo_universal_wordpress`: deactivate the duo plugin. 
Helpful if you've locked yourself out or can't complete 2FA for some reason.
* `wp plugin activate duo_universal_wordpress`: activate the duo plugin
* `wp plugin list`: list all plugins

#### Gotchas
By default, the dev container limits uploads to 2MB. This is too small for 
builds of the plugin, so to upload a build instead of using the mirrored 
build, you'll need to increase the limit.

* Create or edit `/var/www/html/.htaccess` and set `php_value upload_max_filesize 128M` (or whatever value you want)
* For some reason this caps it out at 8 MB regardless of how high the value is set, but that's big enough for the plugin.

### Debug
Add `define( 'WP_DEBUG_LOG', true );` to `/var/www/html/wp-config.php`. Debug
logging will be printed to `/var/www/html/debug.log`.

### Tests
```
composer install
./vendor/bin/phpunit --process-isolation tests
```

### Build
```
composer archive -f zip
```

# Support

Report any bugs, feature requests, etc. to us directly:
support@duosecurity.com

Have fun!

<http://www.duosecurity.com>
