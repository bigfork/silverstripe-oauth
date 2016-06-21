<?php

namespace Bigfork\SilverStripeOAuth\Client\Control;

use Bigfork\SilverStripeOAuth\Client\Factory\ProviderFactory;
use Controller as SilverStripeController;
use Director;
use Injector;
use Member;
use OAuthAccessToken;
use OAuthScope;
use Session;
use SS_HTTPRequest;

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
     * @return string
     */
    public function Link()
    {
        return 'oauth/';
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
        $redirectUri = $request->getVar('redirectUri');

        // Missing or invalid data means we can't proceed
        if (!$providerName || !is_array($scope)) {
            return $this->httpError(404);
        }

        $provider = Injector::inst()->get('Bigfork\SilverStripeOAuth\Client\Factory\ProviderFactory')
            ->createProvider($providerName, $redirectUri);

        $scope = empty($scope) ? $provider->getDefaultScopes() : $scope;
        $url = $provider->getAuthorizationUrl(['scope' => $scope]);

        // Copied from \Controler::redirectBack()
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

        Session::set('oauth2.state', $provider->getState());
        Session::set('oauth2.provider', $providerName);
        Session::set('oauth2.scope', $scope);
        Session::set('oauth2.backurl', $backUrl);

        return $this->redirect($url);
    }

    /**
     * The default return URL after the user has authenticated with a provider
     * 
     * @param SS_HTTPRequest $request
     * @return mixed
     */
    public function callback(SS_HTTPRequest $request)
    {
        if (!$this->validateState($request)) {
            return $this->httpError(400, 'Invalid session state.');
        }

        $providerName = Session::get('oauth2.provider');
        $redirectUri = Controller::join_links(Director::absoluteBaseURL(), $this->owner->Link(), 'callback/');
        $provider = Injector::inst()->get('Bigfork\SilverStripeOAuth\Client\Factory\ProviderFactory')
            ->createProvider($providerName, $redirectUri);
        
        try {
            $token = $provider->getAccessToken('authorization_code', [
                'code' => $request->getVar('code')
            ]);

            $member = Member::currentUser();
            $existingToken = $member->AccessTokens()->filter(['Provider' => $providerName])->first();

            // @todo Should this be opt-in?
            if ($existingToken) {
                $existingToken->delete();
            }

            // Store the access token in the database
            $accessToken = OAuthAccessToken::createFromAccessToken($providerName, $token);
            $accessToken->MemberID = $member->ID;
            $accessToken->write();

            // Record which scopes the access token has
            $scopes = Session::get('oauth2.scope');
            foreach ($scopes as $scope) {
                $scope = OAuthScope::findOrMake($scope);
                $accessToken->Scopes()->add($scope);
            }
        } catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
            return $this->httpError(400, 'Invalid access token.');
        }

        $backUrl = Session::get('oauth2.backurl');
        if (!$backUrl || !Director::is_site_url($backUrl)) {
            $backUrl = Director::baseURL();
        }

        return $this->redirect($backUrl);
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
        $sessionState = Session::get('oauth2.state');
        $sessionProvider = Session::get('oauth2.provider');
        $sessionScope = Session::get('oauth2.scope');

        // If we're lacking any required state data, or the session state
        // doesn't match the one given by the provider, it's not valid
        if (!$state || $state !== $sessionState || !$sessionProvider || !$sessionScope) {
            Session::clear('oauth2.state');
            Session::clear('oauth2.provider');
            Session::clear('oauth2.scope');
            return false;
        }

        return true;
    }
}
