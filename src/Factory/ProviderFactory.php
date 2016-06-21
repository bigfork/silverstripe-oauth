<?php

namespace Bigfork\SilverStripeOAuth\Client\Factory;

use Bigfork\SilverStripeOAuth\Client\Authenticator\Authenticator;
use Config;
use Controller;
use Director;
use Injector;

class ProviderFactory
{
    /**
     * @var array
     */
    private static $providers = [];

    /**
     * @todo Support for collaborators?
     * @param string $name
     * @param string $redirectUri
     * @return League\OAuth2\Client\Provider\AbstractProvider
     */
    public function createProvider($name, $redirectUri = '')
    {
        $providers = Config::inst()->get(__CLASS__, 'providers');
        $config = $providers[$name];

        $constructorOptions = isset($config['constructor_options']) ? $config['constructor_options'] : [];

        if (!$redirectUri) {
            $controller = Injector::inst()->get('Bigfork\SilverStripeOAuth\Client\Control\Controller');
            $redirectUri = Controller::join_links(Director::absoluteBaseURL(), $controller->Link(), 'callback/');
        }

        $data = ['redirectUri' => $redirectUri];
        $provider = Injector::inst()->createWithArgs(
            $config['class'],
            [array_merge($constructorOptions, $data)]
        );

        return $provider;
    }
}
