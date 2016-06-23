<?php

namespace Bigfork\SilverStripeOAuth\Client\Factory;

use Bigfork\SilverStripeOAuth\Client\Authenticator\Authenticator;
use Config;
use Controller;
use Director;
use Injector;
use InvalidArgumentException;

class ProviderFactory
{
    /**
     * @var array
     */
    protected $providers = [];

    /**
     * @param array
     * @return self
     */
    public function setProviders(array $providers)
    {
        $this->providers = $providers;
        return $this;
    }

    /**
     * @return array
     */
    public function getProviders()
    {
        return $this->providers;
    }

    /**
     * @param string $name
     * @return League\OAuth2\Client\Provider\AbstractProvider
     */
    public function getProvider($name)
    {
        $providers = $this->getProviders();

        if (!isset($providers[$name])) {
            throw new InvalidArgumentException("Provider {$name} has not been configured");
        }

        return $providers[$name];
    }
}
