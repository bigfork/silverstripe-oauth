<?php

namespace Bigfork\SilverStripeOAuth\Client\Handler;

use Exception;
use League\OAuth2\Client\Provider\AbstractProvider;
use SilverStripe\Control\HTTPRequest;

interface ErrorHandler
{
    /**
     * @param AbstractProvider $provider
     * @param HTTPRequest $request
     * @param Exception $exception
     * @return void
     */
    public function handleError(AbstractProvider $provider, HTTPRequest $request, Exception $exception);
}
