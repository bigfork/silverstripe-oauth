# SilverStripe OAuth

SilverStripe OAuth2 authentication, based on the PHP League's [OAuth2 client](http://oauth2-client.thephpleague.com/).

### What this module does
This module includes the base functionality for fetching access tokens and associating them with members. It provides methods for creating requests to OAuth providers, fetching access tokens with varyious scopes/permissions, and storing them in the database.

### What this module doesn’t do

This module does not provide “Log in with &lt;provider&gt;” buttons, “Fetch contacts from &lt;provider&gt;” buttons, or any other functionality for actually interacting with providers - it only fetches and stores tokens that will allow you to do that. It’s up to you to install the appropriate ppackages for third-party providers, and to implement functionality that makes use of the access tokens to fetch data from those providers.

If you’re looking for “Log in with &lt;provider&gt;” functionality, take a look at the add-on for this module: [SilverStripe OAuth Login](https://github.com/bigfork/silverstripe-oauth2-login).

## Installation

This module must be installed with composer. Run `composer require bigfork/silverstripe-oauth:*` from the command line, and then run a `dev/build`.

## Configuration

Providers are configured using SilverStripe’s YAML configuration, mapping an “internal” name (passed around in URLs and stored in the database) to a PHP class that’s an instance of the PHP League’s `League\OAuth2\Client\Provider\AbstractProvider` class.

Each provider can have a `constructor_options` array that will be passed to the constructor for the given provider class.

For example, to setup Facebook as a provider, first install the [Facebook OAuth2 package](https://github.com/thephpleague/oauth2-facebook), and then add the following to your YAML config:

```yml
Bigfork\SilverStripeOAuth\Client\Factory\ProviderFactory:
  providers:
    'Facebook': # "Internal" name
      class: 'League\OAuth2\Client\Provider\Facebook' # PHP class name
      constructor_options: # Array to be passed as 1st arg to League\OAuth2\Client\Provider\Facebook::__construct()
        clientId: '12345678987654321'
        clientSecret: 'geisjgoesingoi3h1521onnro12rin'
        graphApiVersion: 'v2.6'
```

---

## Concepts

### Models

This module adds two new models:

#### `OAuthAccessToken`

A single OAuth access token, belonging to a `Member`. Fields include:

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

The module includes one extra controller, `Bigfork\SilverStripeOAuth\Client\Control\Controller`. This controller is responsible for setting up authentication requests, redirecting users to the third-party providers, and checking/handling tokens & redirections when the user returns to the site from the provider.

### Helper

A simple class to help build an authentication request URL to create an access token.

---

## Usage

Below are a few examples of how to perform common actions with fetching/using tokens:

### Check whether a user's token has the given permission

```php
$member = Member::currentUser();
$facebookToken = $member->AccessTokens()->filter(['Provider', 'Facebook'])->first();
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
$member = Member::currentUser();
$facebookToken = $member->AccessTokens()->filter(['Provider', 'Facebook'])->first();
if ($facebookToken->isExpired()) {
    echo 'Oh no, the Facebook token has expired!';
}
```

### Refresh an access token

```php
$member = Member::currentUser();
$facebookToken = $member->AccessTokens()->filter(['Provider', 'Facebook'])->first();
if ($facebookToken->isExpired()) {
    $facebookToken->refresh();
    echo 'Token refreshed successfully';
}
```

---

## Todo

- Unit tests!
- Investigate swapping `class` and `constructor_options` to instead be a service registered via `Injector`. Currently this is limited by the fact that we need to override the constructor arguments to add a `redirectUri` key to the first argument to `League\OAuth2\Client\Provider\AbstractProvider::__construct()`
- Add in support for the `$collaborators` argument for `League\OAuth2\Client\Provider\AbstractProvider::__construct()`. This may depend on the outcome of the above item
- Make the default behaviour of only allowing one access token per provider on each member optional, or just remove it
- Better passing-around of redirect Uris, it's currently a bit messy
