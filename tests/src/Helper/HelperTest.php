<?php

namespace Bigfork\SilverStripeOAuth\Client\Test\Helper;

use Bigfork\SilverStripeOAuth\Client\Helper\Helper;
use Controller;
use Director;
use Injector;
use SapphireTest;

class HelperTest extends SapphireTest
{
    public function testBuildAuthorisationUrl()
    {
        $controller = new HelperTest_Controller;
        Injector::inst()->registerService($controller, 'Bigfork\SilverStripeOAuth\Client\Control\Controller');

        $url = Helper::buildAuthorisationUrl('ProviderName', ['test_scope']);

        $expected = Director::absoluteBaseURL() . 'helpertest/authenticate/';
        $expected .= '?provider=ProviderName&scope%5B0%5D=test_scope';

        $this->assertEquals($expected, $url);
    }
}

class HelperTest_Controller extends Controller
{
    public function Link()
    {
        return 'helpertest/';
    }
}
