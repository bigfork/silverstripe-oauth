<?php

namespace Bigfork\SilverStripeOAuth\Client\Helper;

use Controller;
use Director;
use Injector;

class Helper
{
    /**
     * @param string $provider
     * @param array $scopes
     * @param string $redirectUri
     * @return string
     */
    public static function buildAuthorisationUrl($provider, array $scopes = [], $redirectUri = '')
    {
        $controller = Injector::inst()->get('Bigfork\SilverStripeOAuth\Client\Control\Controller');
        $data = [
            'provider' => $provider,
            'scope' => $scopes,
            'redirectUri' => $redirectUri
        ];

        return Controller::join_links(
            Director::absoluteBaseURL(),
            $controller->Link(),
            'authenticate/?' . http_build_query($data)
        );
    }
}
