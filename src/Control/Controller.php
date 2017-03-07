<?php

namespace Bigfork\SilverStripeOAuth\Client\Control;

use Controller as SilverStripeController;
use Director;
use Exception;
use Injector;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use Member;
use OAuthAccessToken;
use OAuthScope;
use SS_HTTPRequest;
use SS_HTTPResponse;
use SS_Log;

class Controller extends SilverStripeController
{
    /**
     * @var array
     */
    private static $allowed_actions = [
        'authenticate',
        'callback'
    ];

    /**
     * Copied from \Controler::redirectBack()
     *
     * @param SS_HTTPRequest $request
     * @return string|null
     */
    protected function findBackUrl(SS_HTTPRequest $request)
    {
        if ($request->requestVar('BackURL')) {
            $backUrl = $request->requestVar('BackURL');
        } elseif ($request->isAjax() && $request->getHeader('X-Backurl')) {
            $backUrl = $request->getHeader('X-Backurl');
        } elseif ($request->getHeader('Referer')) {
            $backUrl = $request->getHeader('Referer');
        }

        if (!$backUrl || !Director::is_site_url($backUrl)) {
            $backUrl = Director::absoluteBaseURL();
        }

        return $backUrl;
    }

    /**
     * Get the return URL previously stored in session
     *
     * @return string
     */
    protected function getReturnUrl()
    {
        $backUrl = $this->getSession()->inst_get('oauth2.backurl');
        if (!$backUrl || !Director::is_site_url($backUrl)) {
            $backUrl = Director::absoluteBaseURL();
        }

        return $backUrl;
    }

    /**
     * @return string
     */
    public function Link()
    {
        return 'oauth/';
    }

    /**
     * @return string
     */
    public function AbsoluteLink()
    {
        return self::join_links(Director::absoluteBaseURL(), $this->Link());
    }

    /**
     * This takes parameters like the provider, scopes and callback url, builds an authentication
     * url with the provider's site and then redirects to it
     *
     * @todo allow whitelisting of scopes (per provider)?
     * @param SS_HTTPRequest $request
     * @return mixed
     */
    public function authenticate(SS_HTTPRequest $request)
    {
        $providerName = $request->getVar('provider');
        $scope = $request->getVar('scope');

        // Missing or invalid data means we can't proceed
        if (!$providerName || !is_array($scope)) {
            return $this->httpError(404);
        }

        $provider = Injector::inst()->get('ProviderFactory')->getProvider($providerName);

        // Ensure we always have scope to work with
        if (empty($scope)) {
            $scope = $provider->getDefaultScopes();
        }

        $url = $provider->getAuthorizationUrl(['scope' => $scope]);

        $this->getSession()->inst_set('oauth2', [
            'state' => $provider->getState(),
            'provider' => $providerName,
            'scope' => $scope,
            'backurl' => $this->findBackUrl($request)
        ]);

        return $this->redirect($url);
    }

    /**
     * The return endpoint after the user has authenticated with a provider
     *
     * @param SS_HTTPRequest $request
     * @return mixed
     */
    public function callback(SS_HTTPRequest $request)
    {
        if (!$this->validateState($request)) {
            return $this->httpError(400, 'Invalid session state.');
        }

        $session = $this->getSession();
        $providerName = $session->inst_get('oauth2.provider');
        $provider = Injector::inst()->get('ProviderFactory')->getProvider($providerName);

        try {
            $accessToken = $provider->getAccessToken('authorization_code', [
                'code' => $request->getVar('code')
            ]);

            // Store the access token in the database
            $token = $this->storeAccessToken($accessToken, $providerName);

            // Run extensions to process the token
            $results = $this->extend('afterGetAccessToken', $provider, $token, $providerName, $request);
            foreach ($results as $result) {
                if ($result instanceof SS_HTTPResponse) {
                    return $result;
                }
            }
        } catch (IdentityProviderException $e) {
            SS_Log::log('OAuth IdentityProviderException: ' . $e->getMessage(), SS_Log::ERR);
            return $this->httpError(400, 'Invalid access token.');
        } catch (Exception $e) {
            SS_Log::log('OAuth Exception: ' . $e->getMessage(), SS_Log::ERR);
            return $this->httpError(400, $e->getMessage());
        }

        return $this->redirect($this->getReturnUrl());
    }

    /**
     * Store the access token in the database. Also gets token scope info from session
     *
     * @param AccessToken $accessToken
     * @param string $provider
     * @return OAuthAccessToken
     */
    protected function storeAccessToken(AccessToken $accessToken, $provider)
    {
        $accessToken = OAuthAccessToken::createFromAccessToken($provider, $accessToken);
        $accessToken->write();

        // Record which scopes the access token has
        $scopes = $this->getSession()->inst_get('oauth2.scope');
        foreach ($scopes as $scope) {
            $scope = OAuthScope::findOrMake($scope);
            $accessToken->Scopes()->add($scope);
        }

        return $accessToken;
    }

    /**
     * Validate the request's state against the one stored in session
     *
     * @param SS_HTTPRequest $request
     * @return boolean
     */
    public function validateState(SS_HTTPRequest $request)
    {
        $state = $request->getVar('state');
        $session = $this->getSession();
        $data = $session->inst_get('oauth2');

        // If we're lacking any required data, or the session state doesn't match
        // the one the provider returned, the request is invalid
        if (empty($data['state']) || empty($data['provider']) || empty($data['scope']) || $state !== $data['state']) {
            $session->inst_clear('oauth2');
            return false;
        }

        return true;
    }
}
