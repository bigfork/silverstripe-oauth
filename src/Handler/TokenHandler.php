<?php

namespace Bigfork\SilverStripeOAuth\Client\Handler;

use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Token\AccessToken;
use SilverStripe\Control\HTTPResponse;

interface TokenHandler
{
    /**
     * @param AccessToken $token
     * @param AbstractProvider $provider
     * @return HTTPResponse|null
     */
    public function handleToken(AccessToken $token, AbstractProvider $provider);
}
