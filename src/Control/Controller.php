<?php

namespace Bigfork\SilverStripeOAuth\Client\Control;

use Exception;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Psr\Log\LoggerInterface;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\Controller as SilverStripeController;
use SilverStripe\Core\Injector\Injector;
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
     * @var string
     */
    private static $url_segment = 'oauth';

    /**
     * Copied from \SilverStripe\Control\Controller::redirectBack()
     *
     * @param HTTPRequest $request
     * @return string|null
     */
    protected function findBackUrl(HTTPRequest $request)
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
        $backUrl = $this->getRequest()->getSession()->get('oauth2.backurl');
        if (!$backUrl || !Director::is_site_url($backUrl)) {
            $backUrl = Director::absoluteBaseURL();
        }

        return $backUrl;
    }

    /**
     * @return string
     */
    public function AbsoluteLink()
    {
        return static::join_links(Director::absoluteBaseURL(), $this->Link());
    }

    /**
     * This takes parameters like the provider, scopes and callback url, builds an authentication
     * url with the provider's site and then redirects to it
     *
     * @todo allow whitelisting of scopes (per provider)?
     * @param HTTPRequest $request
     * @return HTTPResponse
     * @throws \SilverStripe\Control\HTTPResponse_Exception
     */
    public function authenticate(HTTPRequest $request)
    {
        $providerName = $request->getVar('provider');
        $context = $request->getVar('context');
        $scope = $request->getVar('scope');

        // Missing or invalid data means we can't proceed
        if (!$providerName || !is_array($scope)) {
            $this->httpError(404);
        }

        $provider = Injector::inst()->get('ProviderFactory')->getProvider($providerName);

        // Ensure we always have scope to work with
        if (empty($scope)) {
            $scope = $provider->getDefaultScopes();
        }

        $url = $provider->getAuthorizationUrl(['scope' => $scope]);

        $request->getSession()->set('oauth2', [
            'state' => $provider->getState(),
            'provider' => $providerName,
            'context' => $context,
            'scope' => $scope,
            'backurl' => $this->findBackUrl($request)
        ]);

        return $this->redirect($url);
    }

    /**
     * The return endpoint after the user has authenticated with a provider
     *
     * @param HTTPRequest $request
     * @return mixed
     */
    public function callback(HTTPRequest $request)
    {
        $session = $request->getSession();

        if (!$this->validateState($request)) {
            $session->clear('oauth2');
            return $this->httpError(400, 'Invalid session state.');
        }

        $providerName = $session->get('oauth2.provider');
        $provider = Injector::inst()->get('ProviderFactory')->getProvider($providerName);
        $returnUrl = $this->getReturnUrl();

        try {
            $accessToken = $provider->getAccessToken('authorization_code', [
                'code' => $request->getVar('code')
            ]);

            $handlers = $this->getHandlersForContext($session->get('oauth2.context'));

            // Run handlers to process the token
            $results = [];
            foreach ($handlers as $handlerConfig) {
                $handler = Injector::inst()->create($handlerConfig['class']);
                $results[] = $handler->handleToken($accessToken, $provider);
            }

            // Handlers may return response objects
            foreach ($results as $result) {
                if ($result instanceof HTTPResponse) {
                    $session->clear('oauth2');
                    return $result;
                }
            }
        } catch (IdentityProviderException $e) {
            $logger = Injector::inst()->get(LoggerInterface::class);
            $logger->error('OAuth IdentityProviderException: ' . $e->getMessage());
            return $this->httpError(400, 'Invalid access token.');
        } catch (Exception $e) {
            $logger = Injector::inst()->get(LoggerInterface::class);
            $logger->error('OAuth Exception: ' . $e->getMessage());
            return $this->httpError(400, $e->getMessage());
        } finally {
            $session->clear('oauth2');
        }

        return $this->redirect($returnUrl);
    }

    /**
     * Get a list of token handlers for the given context
     *
     * @param string|null $context
     * @return array
     * @throws Exception
     */
    protected function getHandlersForContext($context = null)
    {
        $handlers = $this->config()->get('token_handlers');
        if (empty($handlers)) {
            throw new Exception('No token handlers were registered');
        }

        // If we've been given a context, limit to that context + global handlers.
        // Otherwise only allow global handlers (i.e. exclude named ones)
        $allowedContexts = ['*'];
        if ($context) {
            $allowedContexts[] = $context;
        }

        // Filter handlers by context
        $handlers = array_filter($handlers, function ($handler) use ($allowedContexts) {
            return in_array($handler['context'], $allowedContexts);
        });

        // Sort handlers by priority
        uasort($handlers, function ($a, $b) {
            if (!array_key_exists('priority', $a) || !array_key_exists('priority', $b)) {
                return 0;
            }

            return ($a['priority'] < $b['priority']) ? -1 : 1;
        });

        return $handlers;
    }

    /**
     * Validate the request's state against the one stored in session
     *
     * @param HTTPRequest $request
     * @return boolean
     */
    public function validateState(HTTPRequest $request)
    {
        $state = $request->getVar('state');
        $session = $request->getSession();
        $data = $session->get('oauth2');

        // If we're lacking any required data, or the session state doesn't match
        // the one the provider returned, the request is invalid
        if (empty($data['state']) || empty($data['provider']) || empty($data['scope']) || $state !== $data['state']) {
            $session->clear('oauth2');
            return false;
        }

        return true;
    }
}
