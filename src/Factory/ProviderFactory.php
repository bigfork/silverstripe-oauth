<?php

namespace Bigfork\SilverStripeOAuth\Client\Factory;

use InvalidArgumentException;
use League\OAuth2\Client\Provider\AbstractProvider;

class ProviderFactory
{
    protected $providers = [];

    /**
     * @param array $providers
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
     * @return AbstractProvider
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
