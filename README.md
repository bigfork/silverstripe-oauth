# SilverStripe OAuth

SilverStripe OAuth2 authentication, based on the PHP League's [OAuth2 client](http://oauth2-client.thephpleague.com/).

## \*\* IMPORTANT \*\*

Please note that this module is still in early development and should **not** be used in a production environment. It has not been fully tested, and may undergo significant changes before a stable release.

### What this module does
This module includes the base functionality for fetching access tokens. It provides methods for creating requests to OAuth providers, fetching access tokens with various scopes/permissions, and storing them in the database.

### What this module doesn’t do

This module does not provide “Log in with &lt;provider&gt;” buttons, “Fetch contacts from &lt;provider&gt;” buttons, or any other functionality for actually interacting with providers - it only fetches and stores tokens that will allow you to do that. It’s up to you to install the appropriate packages for third-party providers, and to implement functionality that makes use of the access tokens to fetch data from those providers.

If you’re looking for “Log in with &lt;provider&gt;” functionality, take a look at the add-on for this module: [SilverStripe OAuth Login](https://github.com/bigfork/silverstripe-oauth-login).

## Installation

This module must be installed with composer. Run `composer require bigfork/silverstripe-oauth:*` from the command line, and then run a `dev/build`.

## Configuration

Providers are registered as `Injector` services using SilverStripe’s YAML configuration. This allows you to specify an “internal” name (passed around in URLs and stored in the database), a PHP class for the provider (that extends `League\OAuth2\Client\Provider\AbstractProvider`), and constructor parameters & class properties.

For example, to setup Facebook as a provider, first install the [Facebook OAuth2 package](https://github.com/thephpleague/oauth2-facebook), and then add the following to your YAML config:

```yml
Injector:
  ProviderFactory:
    properties:
      providers:
        'Facebook': '%$FacebookProvider'
  FacebookProvider:
    class: 'League\OAuth2\Client\Provider\Facebook'
    constructor:
      Options:
        clientId: '12345678987654321'
        clientSecret: 'geisjgoesingoi3h1521onnro12rin'
        graphApiVersion: 'v2.6'
```

Note that in the above example, the required `redirectUri` constructor argument is missing. This module will automatically update the service configuration to add this argument to all providers, to save having to update the URL when moving between environments/domain names. If the `redirectUri` argument is present, it will not be overridden.

---

## Concepts

### Models

This module adds two new models:

#### `OAuthAccessToken`

A single OAuth access token. Fields include:

- `Token` - the access token itself
- `Provider` - the provider name (“internal” name - see [Configuration](#configuration))
- `RefreshToken` - the refresh token (optional)
- `Expires` - the token expiry date (optional)
- `ResourceOwnerID` - the resource owner ID (optional)

This model also has a `many_many` relation to [`OAuthScope`](#OAuthScope).

#### `OAuthScope`

A scope (or “permission”) that a token has. This is simply a one-column table (`Name`) that stores a list of scopes that _all_ providers may share. Note that scopes in this table are not unique to each provider (for example, Facebook 'email' and Google 'email' scopes will share the same database record).

Has a `belongs_many_many` relation to [`OAuthAccessToken`](#OAuthAccessToken).

### Controller

The module includes one extra controller, `Bigfork\SilverStripeOAuth\Client\Control\Controller`. This controller is responsible for setting up authentication requests, redirecting users to the third-party providers, and checking/handling tokens & redirections when the user returns to the site from the provider. This module also passes the returned access token to extensions to allow them to decide how the token should be used.

### Helper

A simple class to help build an authentication request URL to create an access token. Also responsible for ensuring the `redirectUri` option is set in each provider’s service configuration.

---

## Usage

Below are a few examples of how to perform common actions with fetching/using tokens:

### Add a token to a model

The module provides an `afterGetAccessToken()` extension hook, which allows extensions to decide how to handle the access token after it has been stored in the database. Throwing an exception in this method will result in the exception message being logged, and a "400 Bad Request" error page being shown. The method can also return an instance of `SS_HTTPResponse` which will be output to the browser after all remaining extensions have been run.

Below is a simplified example which will store the access token against an "Account" record.

```yml
Bigfork\SilverStripeOAuth\Client\Control\Controller:
  extensions:
    - MyControllerExtension
```

```php
class MyControllerExtension extends Extension
{
    public function afterGetAccessToken(OAuthAccessToken $token, SS_HTTPRequest $request)
    {
        $accountID = Session::get('Account.ID'); // Stored before redirecting to '/oauth/authenticate'
        $token->AccountID = $accountID;
        $token->write();
    }
}
```

### Check whether a user's token has the given permission

```php
$facebookToken = OAuthAccessToken::get()->filter(['Provider', 'Facebook'])->first();
if (!$facebookToken->includesScope('user_friends')) {
    echo 'Unable to access friends list';
}
```

### Request an access token

```php
use Bigfork\SilverStripeOAuth\Client\Helper\Helper;

// Build a URL for fetching a Facebook access token with the 'email' and 'user_friends' permissions
// Will return a URL like: http://mysite.com/oauth/authenticate/?provider=Facebook&scope%5B0%5D=email&scope%5B2%5D=user_friends
$url = Helper::buildAuthorisationUrl('Facebook', ['email', 'user_friends']);
echo "<a href=" . $url . ">Connect to Facebook</a>";
```

### Check whether a token is expired
```php
$facebookToken = OAuthAccessToken::get()->filter(['Provider', 'Facebook'])->first();
if ($facebookToken->isExpired()) {
    echo 'Oh no, the Facebook token has expired!';
}
```

### Refresh an access token

```php
$facebookToken = OAuthAccessToken::get()->filter(['Provider', 'Facebook'])->first();
if ($facebookToken->isExpired()) {
    $facebookToken->refresh();
    echo 'Token refreshed successfully';
}
```
