[![GitHub license](https://img.shields.io/github/license/fawno/NTLMAuthenticator)](https://github.com/fawno/NTLMAuthenticator/blob/master/LICENSE)
[![GitHub release](https://img.shields.io/github/release/fawno/NTLMAuthenticator)](https://github.com/fawno/NTLMAuthenticator/releases)
[![Packagist](https://img.shields.io/packagist/v/fawno/ntlm-authentication)](https://packagist.org/packages/fawno/ntlm-authentication)
[![Packagist Downloads](https://img.shields.io/packagist/dt/fawno/ntlm-authentication)](https://packagist.org/packages/fawno/ntlm-authentication/stats)
[![GitHub issues](https://img.shields.io/github/issues/fawno/NTLMAuthenticator)](https://github.com/fawno/NTLMAuthenticator/issues)
[![GitHub forks](https://img.shields.io/github/forks/fawno/NTLMAuthenticator)](https://github.com/fawno/NTLMAuthenticator/network)
[![GitHub stars](https://img.shields.io/github/stars/fawno/NTLMAuthenticator)](https://github.com/fawno/NTLMAuthenticator/stargazers)

# NTLM Authenticator for CakePHP 4 Authentication plugin

This plugin provides an NTLM Authenticator for CakePHP 4 authentication plugin.

# Table of contents
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
  - [Apache with SSPI NTLM based authentication module (mod_authn_ntlm)](#apache-with-sspi-ntlm-based-authentication-module-mod_authn_ntlm)
  - [NTLMAuthenticator](#ntlmauthenticator)

## Requirements

- PHP >= 7.2.0
- Apache 2.4 SSPI NTLM based authentication module ([mod_authn_ntlm](https://github.com/TQsoft-GmbH/mod_authn_ntlm))
- CakePHP >= 4.3.0
- [CakePHP Authentication](https://book.cakephp.org/authentication/2/en/index.html) >= 2.0

Optional:
- ext-ldap ([LDAP php extension](https://www.php.net/manual/en/book.ldap.php))

[TOC](#table-of-contents)

## Installation

Install this plugin into your application using [composer](https://getcomposer.org):

- Add `fawno/ntlm-authentication` package to your project:
  ```bash
    composer require fawno/ntlm-authentication
  ```
- Load the NTLMAuthenticator in your Application.php:
  ```php
  use Fawno\NTLM\Authenticator\NTLMAuthenticator;
  ```
- Load the NTLMAuthenticator in your Authentication Service (Application.php):
  ```php
  // Load the authenticators. Session should be first.
  $service->loadAuthenticator('Authentication.Session');

  $service->loadAuthenticator(NTLMAuthenticator::class, [
      'domains' => [],
  ]);
  ```

[TOC](#table-of-contents)

## Configuration

`exampledomain` short domain name

`example.com` full domain name

### Apache with SSPI NTLM based authentication module ([mod_authn_ntlm](https://github.com/TQsoft-GmbH/mod_authn_ntlm))

Only routes with /login are authenticated with NTLM
`webroot\.htaccess`:
```aconf
<If "%{THE_REQUEST} =~ m#GET .*/login(\?.*)? HTTP.*#">
	AuthName "Example App"
	AuthType SSPI
	NTLMAuth On
	NTLMAuthoritative On
	NTLMDomain exampledomain
	NTLMOmitDomain Off     # keep domain name in userid string
	NTLMOfferBasic On      # let non-IE clients authenticate
	NTLMBasicPreferred Off # should basic authentication have higher priority
	NTLMUsernameCase lower
	Require valid-user
</If>
<Else>
	AuthType None
	Require all granted
</Else>

#Order allow,deny
#Allow from 192.168.0.0/16
Satisfy all
```

[TOC](#table-of-contents)

### NTLMAuthenticator

NTLM Authenticator can query through LDAP for user membership. This information is stored in the session and can be used for authorization (ACL).

```php
$service->loadAuthenticator(NTLMAuthenticator::class, [
    'domains' => [
        'exampledomain' => [
            'ldap' => [
                'srv' => 'active-directory.example.com',
                'user' => base64_encode('user@example.com'),
                'pass' => base64_encode('UserPassword'),
                'dn' => 'OU=Departaments, DC=example, DC=com',
                'dn_users' => 'CN=Users, DC=example, DC=com',
            ],
            'config' => [
                'some_key' => 'some_data',
            ],
        ],
        'exampledomain2' => [
            'ldap' => [
                'srv' => 'active-directory.example2.com',
                'user' => base64_encode('user@example2.com'),
                'pass' => base64_encode('UserPassword2'),
                'dn' => 'OU=Departaments, DC=example2, DC=com',
                'dn_users' => 'CN=Users, DC=example2, DC=com',
            ],
            'config' => [
                'some_key' => 'some_data',
            ],
        ],
    ],
]);
```
The configured credentials should have query-only access to the LDAP service and no other privileges within the domain.

`config` array is optional data can be stored in session auth data.
It allows configuring the logo of the organization and other data common to the users of a domain that the application needs to use.

The application does not have any access to validated user passwords, all NTLM authentication is negotiated between the Apache server and the browser.

[TOC](#table-of-contents)
