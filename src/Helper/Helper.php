<?php

namespace Bigfork\SilverStripeOAuth\Client\Helper;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\Control\Director;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Config\Config;

class Helper
{
    /**
     * @var string
     */
    private static $default_redirect_uri;

    /**
     * @param string $provider The OAuth provider name (as configured in YAML)
     * @param string $context The context from which the token is being requested, e.g. 'login'
     * @param array $scopes An array of OAuth "scopes" required
     * @return string
     */
    public static function buildAuthorisationUrl($provider, $context = '', array $scopes = [])
    {
        $controller = Injector::inst()->get('Bigfork\SilverStripeOAuth\Client\Control\Controller');
        $data = [
            'provider' => $provider,
            'context' => $context,
            'scope' => $scopes
        ];

        return Controller::join_links(
            Director::absoluteBaseURL(),
            $controller->Link(),
            'authenticate/?' . http_build_query($data)
        );
    }

    /**
     * Adds the redirectUri option to each of the configured provider's service
     * configs: the redirectUri is required on construction
     */
    public static function addRedirectUriToConfigs()
    {
        $factoryConfig = Config::inst()->get(Injector::class, 'ProviderFactory');
        $providers = $factoryConfig['properties']['providers'];

        foreach ($providers as $name => $spec) {
            // If this is not a service definition, skip it
            if (strpos($spec, '%$') !== 0) {
                continue;
            }

            // Trim %$ServiceName to ServiceName
            $serviceName = substr($spec, 2);
            $serviceConfig = (array)Config::inst()->get(Injector::class, $serviceName);

            if (!empty($serviceConfig)) {
                $serviceConfig = static::addRedirectUriToServiceConfig($serviceConfig);
                Config::inst()->update(Injector::class, $serviceName, $serviceConfig);
                Injector::inst()->load(array($serviceName => $serviceConfig));
            }
        }
    }

    /**
     * Add in the redirectUri option to this service's constructor options
     *
     * @param array $config
     * @return array
     */
    protected static function addRedirectUriToServiceConfig(array $config)
    {
        if (!empty($config['constructor']) && is_array($config['constructor'])) {
            $key = key($config['constructor']); // Key may be non-numeric

            if (!isset($config['constructor'][$key]['redirectUri'])) {
                $config['constructor'][$key]['redirectUri'] = static::getRedirectUri();
            }
        }

        return $config;
    }

    /**
     * @return string
     */
    protected static function getRedirectUri()
    {
        $configUri = Config::inst()->get(__CLASS__, 'default_redirect_uri');
        if ($configUri) {
            return $configUri;
        }

        $controller = Injector::inst()->get('Bigfork\SilverStripeOAuth\Client\Control\Controller');
        return Controller::join_links($controller->AbsoluteLink(), 'callback/');
    }
}
