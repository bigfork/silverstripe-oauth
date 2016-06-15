<?php

namespace Bigfork\SilverStripeOAuth\Client\Authenticator;

use Bigfork\SilverStripeOAuth\Client\Form\LoginForm;
use Config;
use Controller;
use Injector;

class Authenticator extends \MemberAuthenticator
{
    /**
     * @return LoginForm
     */
    public static function get_login_form(Controller $controller)
    {
        // @todo Use LoginForm::class in SS4 - it currently breaks SS_ConfigStaticManifest_Parser
        return Injector::inst()->create('Bigfork\SilverStripeOAuth\Client\Form\LoginForm', $controller, 'LoginForm');
    }

    /**
     * @return string
     */
    public static function get_name()
    {
        // @todo Use static::class in SS4 - it currently breaks SS_ConfigStaticManifest_Parser
        return _t('Bigfork\SilverStripeOAuth\Client\Authenticator\Authenticator.TITLE', 'Social sign-on');
    }
}
