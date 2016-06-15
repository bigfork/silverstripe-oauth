<?php

namespace Bigfork\SilverStripeOAuth\Client\Factory;

use Bigfork\SilverStripeOAuth\Client\Authenticator\Authenticator;
use Config;
use Injector;

class ProviderFactory
{
	/**
	 * @todo Support for collaborators?
	 * @param string $name
	 * @return League\OAuth2\Client\Provider\AbstractProvider
	 */
	public function createProvider($name)
	{
		// @todo Use Authenticator::class in SS4 - it currently breaks SS_ConfigStaticManifest_Parser
		$providers = Config::inst()->get('Bigfork\SilverStripeOAuth\Client\Authenticator\Authenticator', 'providers');
        $config = $providers[$name];

        $constructorOptions = isset($config['constructor_options']) ? $config['constructor_options'] : [];

        $data = ['redirectUri' => 'http://oauth.dev/oauth/authenticate/'];
        $provider = Injector::inst()->createWithArgs(
            $config['class'],
            [array_merge($constructorOptions, $data)]
        );

        return $provider;
	}
}
