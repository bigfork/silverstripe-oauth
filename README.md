# SilverStripe OAuth

SilverStripe OAuth2 authentication, based on the PHP League's [OAuth2 client](http://oauth2-client.thephpleague.com/).

### What this module does

This module includes the base functionality for fetching access tokens. It provides methods for creating requests to OAuth providers, fetching access tokens with various scopes/permissions, and registering handlers for dealing with the returned tokens.

### What this module doesn’t do

This module does not provide “Log in with &lt;provider&gt;” buttons, “Fetch contacts from &lt;provider&gt;” buttons, or any other functionality for actually interacting with providers - it only fetches tokens that will allow you to do that. It’s up to you to install the appropriate packages for third-party providers, and to implement functionality that makes use of the access tokens to fetch data from those providers.

If you’re looking for “Log in with &lt;provider&gt;” functionality, take a look at the add-on for this module: [SilverStripe OAuth Login](https://github.com/bigfork/silverstripe-oauth-login).

This module also does not store access tokens in the database. If this is a requirement of your application, you will need to build your own models to handle this, and set up appropriate token handlers.

## Installation

This module must be installed with composer. Run `composer require bigfork/silverstripe-oauth:*` from the command line, and then run a `dev/build`.

## Configuration

Providers are registered as `Injector` services using SilverStripe’s YAML configuration. This allows you to specify an “internal” name (passed around in URLs and session data), a PHP class for the provider (that extends `League\OAuth2\Client\Provider\AbstractProvider`), and constructor parameters & class properties.

For example, to setup Facebook as a provider, first install the [Facebook OAuth2 package](https://github.com/thephpleague/oauth2-facebook), and then add the following to your YAML config:

```yml
SilverStripe\Core\Injector\Injector:
  Bigfork\SilverStripeOAuth\Client\Factory\ProviderFactory:
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

## Usage

##### If you’re looking for “Log in with &lt;provider&gt;” functionality, take a look at the add-on for this module: [SilverStripe OAuth Login](https://github.com/bigfork/silverstripe-oauth-login).

In order to actually interact with an OAuth token, you’ll need to register a token handler (which implements `Bigfork\SilverStripeOAuth\Client\Handler\TokenHandler`) to do so as part of the callback process. Each handler has an optional numeric priority (to control the order in which they are called), and a “context”. The context option is used to ensure that the handler is only run when certain actions are performed, and matches up to the context parameter specified when issuing a token request (see the [Helper](#helper) section). Handlers registered with a context of `*` will always be called, regardless of the context provided.

Below is an example of a token handler responsible for fetching events from a user’s Facebook profile, and how to register it. We use the context parameter to ensure that the handler is only run when performing this action (we don’t want it to run when the user is logging in, for example).

Here we register our token handler, with a context named `import_events`:

```yml
Bigfork\SilverStripeOAuth\Client\Control\Controller:
  token_handlers:
    importeventshandler:
      priority: 1
      context: 'import_events'
      class: 'ImportEventsHandler'
```

Next, we need to build an authorisation URL with that context specified:

```php
use Bigfork\SilverStripeOAuth\Client\Helper\Helper;

// Build a URL for fetching a Facebook access token with the required 'user_events' permission
// Will return a URL like: http://mysite.com/oauth/authenticate/?provider=Facebook&context=import_events&scope%5B2%5D=user_events
$url = Helper::buildAuthorisationUrl('Facebook', 'import_events', ['user_events']);
echo "<a href=" . $url . ">Import events from Facebook</a>";
```

When the user returns from Facebook, our token handler will be called as part of the callback process:

```php
use Bigfork\SilverStripeOAuth\Client\Handler\TokenHandler;
use League\OAuth2\Client\Provider\Facebook;
use League\OAuth2\Client\Token\AccessToken;

class ImportEventsHandler implements TokenHandler
{
    public function handleToken(AccessToken $token, Facebook $provider)
    {
        $baseUrl = 'https://graph.facebook.com/v2.8';
        $params = http_build_query([
            'fields' => 'id,name,start_time',
            'limit' => '5',
            'access_token' => $token->getToken(),
            'appsecret_proof' => hash_hmac('sha256', $token->getToken(), '{facebook-app-secret}'),
        ]);
        $response = file_get_contents($baseUrl.'/me/events?'.$params);
        $data = json_decode($response, true);

        $this->importEvents($data);
    }
}
```

Throwing an exception from the `handleToken()` method will result in all other handlers being cancelled, the exception message being logged, and a "400 Bad Request" error page being shown to the user. The method can also return an instance of `SS_HTTPResponse` which will be output to the browser after all remaining handlers have been run.

## Error handling

Sometimes, OAuth providers may return errors that have been caused by the user. The most common example of this is when a user rejects granting the permissions you’ve requested. As each provider provides error messages in different ways, you’ll need to build your own error handling logic. Error handlers can be registered similarly to token handlers, for example:

```yml
Bigfork\SilverStripeOAuth\Client\Control\Controller:
  error_handlers:
    importeventserrorhandler:
      priority: 1
      context: 'import_events'
      class: 'ImportEventsErrorHandler'
```

```php
use Exception;
use League\OAuth2\Client\Provider\AbstractProvider;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Security\Security;

class ImportEventsErrorHandler implements ErrorHandler
{
    public function handleError(AbstractProvider $provider, HTTPRequest $request, Exception $exception)
    {
        if ($request->getVar('error_message')) {
            return Security::permissionFailure(null, $request->getVar('error_message'));
        }
    }
}
```

## Error logging

By default, fatal OAuth errors will be logged to PHP’s error log. You can set up your own logging by overriding the `Psr\Log\LoggerInterface.oauth:` Injector service definition. For example, to log errors to an `oauth.log` file:

```yml
---
After: silverstripe-oauth-logging
---
SilverStripe\Core\Injector\Injector:
  Psr\Log\LoggerInterface.oauth:
    calls: null # Reset - without this, the below "calls" would be merged in instead of replacing the original
---
After: silverstripe-oauth-logging
---
SilverStripe\Core\Injector\Injector:
  Psr\Log\LoggerInterface.oauth:
    calls:
      pushDisplayErrorHandler: [ pushHandler, [ '%$OAuthLogFileHandler' ] ]
  OAuthLogFileHandler:
    class: Monolog\Handler\StreamHandler
    constructor:
       - '../oauth.log'
       - 'error'
```

## SameSite / POST cookies

Some providers, like Apple, will redirect back to your site with a POST request. When this happens, the default browser behaviour is not to send cookies with the request, meaning that it’s not possible to verify the request as the user’s session is lost. There are two ways to work around this:

1. Mark your session cookie as `SameSite=None; Secure` as detailed [here](https://docs.silverstripe.org/en/4/developer_guides/cookies_and_sessions/sessions/#samesite-attribute)
2. Set the following YAML value:

```yml
Bigfork\SilverStripeOAuth\Client\Control\Controller:
  enable_samesite_workaround_redirect: true
```

When this is enabled, your site will handle the POST that it receives from your OAuth provider by redirecting the user via a GET request. When this GET request is issued, the site once again has access to the user’s session cookie so the authorisation process can continue as normal.
