<?php

namespace Bigfork\SilverStripeOAuth\Client\Control;

use Controller as SilverStripeController;
use Director;
use Injector;
use League\OAuth2\Client\Token\AccessToken;
use Member;
use OAuthAccessToken;
use OAuthScope;
use SS_HTTPRequest;
use SS_HTTPResponse;

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
     * @var Member
     */
    protected $member;

    /**
     * @param Member $member
     * @return self
     */
    public function setMember(Member $member)
    {
        $this->member = $member;
        return $this;
    }

    /**
     * @return Member|null
     */
    public function getMember()
    {
        if ($this->member) {
            return $this->member;
        }

        return Member::currentUser();
    }

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
            $backUrl = Director::baseURL();
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
            $backUrl = Director::baseURL();
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
            $token = $provider->getAccessToken('authorization_code', [
                'code' => $request->getVar('code')
            ]);

            $results = $this->extend('afterGetAccessToken', $provider, $token, $providerName, $request);
            foreach ($results as $result) {
                if ($result instanceof SS_HTTPResponse) {
                    return $result;
                }
            }

            $member = $this->getMember();

            // Clear existing tokens for this provider - @todo
            $member->clearTokensFromProvider($providerName);
            // Store the access token in the database
            $this->storeAccessToken($member, $token, $providerName);
        } catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
            return $this->httpError(400, 'Invalid access token.');
        }

        return $this->redirect($this->getReturnUrl());
    }

    /**
     * Store the access token against a member. Also gets token scope info from session
     * 
     * @param Member $member
     * @param AccessToken $token
     * @param string $provider
     */
    protected function storeAccessToken(Member $member, AccessToken $token, $provider)
    {
        $accessToken = OAuthAccessToken::createFromAccessToken($provider, $token);
        $accessToken->MemberID = $member->ID;
        $accessToken->write();

        // Record which scopes the access token has
        $scopes = $this->getSession()->inst_get('oauth2.scope');
        foreach ($scopes as $scope) {
            $scope = OAuthScope::findOrMake($scope);
            $accessToken->Scopes()->add($scope);
        }
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
