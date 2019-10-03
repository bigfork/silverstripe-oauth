<?php

namespace Bigfork\SilverStripeOAuth\Client\Handler;

use League\OAuth2\Client\Provider\AbstractProvider;
use SilverStripe\Control\HTTPRequest;

interface ErrorHandler
{
    /**
     * @param string $message
     * @param AbstractProvider $provider
     * @param HTTPRequest $request
     * @return void
     */
    public function handleError(&$message, AbstractProvider $provider, HTTPRequest $request);
}
