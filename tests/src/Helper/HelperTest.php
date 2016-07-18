<?php

namespace Bigfork\SilverStripeOAuth\Client\Test\Helper;

use Bigfork\SilverStripeOAuth\Client\Helper\Helper;
use Bigfork\SilverStripeOAuth\Client\Test\TestCase;
use Config;
use Controller;
use Director;
use Injector;
use ReflectionMethod;

class HelperTest extends TestCase
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

    public function testAddRedirectUriToConfigs()
    {
        // Store originals
        $config = Config::inst();
        $injector = Injector::inst();

        $uri = 'http://mysite.com/endpoint';

        $factoryConfig = ['properties' => ['providers' => ['ProviderName' => '%$ProviderService']]];
        $originalConfig = ['constructor' => ['Options' => ['apiKey' => '123']]];
        $expectedConfig = ['constructor' => ['Options' => ['apiKey' => '123', 'redirectUri' => $uri]]];

        $mockConfig = $this->getMock('Config', ['get', 'update']);
        $mockConfig->expects($this->at(0))
            ->method('get')
            ->with('Injector', 'ProviderFactory')
            ->will($this->returnValue($factoryConfig));
        $mockConfig->expects($this->at(1))
            ->method('get')
            ->with('Injector', 'ProviderService')
            ->will($this->returnValue($originalConfig));
        // This smells, because it makes assumptions about methods other than the one we're testing.
        // But mocking static methods sucks.
        $mockConfig->expects($this->at(2))
            ->method('get')
            ->with('Bigfork\SilverStripeOAuth\Client\Helper\Helper', 'default_redirect_uri')
            ->will($this->returnValue($uri));
        $mockConfig->expects($this->at(3))
            ->method('update')
            ->with('Injector', 'ProviderService')
            ->will($this->returnValue($expectedConfig));

        $mockInjector = $this->getMock('Injector', ['load']);
        $mockInjector->expects($this->once())
            ->method('load')
            ->with(['ProviderService' => $expectedConfig]);

        // Inject mocks
        Config::set_instance($mockConfig);
        Injector::set_inst($mockInjector);

        // Run the test
        Helper::addRedirectUriToConfigs();

        // Restore things
        Config::set_instance($config);
        Injector::set_inst($injector);
    }

    public function testAddRedirectUriToServiceConfig()
    {
        $uri = 'http://mysite.com/endpoint';
        Config::inst()->update('Bigfork\SilverStripeOAuth\Client\Helper\Helper', 'default_redirect_uri', $uri);

        // This assertion smells, because it makes assumptions about methods other than the one we're testing.
        // But mocking static methods sucks.
        $originalConfig = ['constructor' => ['Options' => ['apiKey' => '123']]];
        $expectedConfig = ['constructor' => ['Options' => ['apiKey' => '123', 'redirectUri' => $uri]]];

        $reflectionMethod = new ReflectionMethod(
            'Bigfork\SilverStripeOAuth\Client\Helper\Helper',
            'addRedirectUriToServiceConfig'
        );
        $reflectionMethod->setAccessible(true);

        $result = $reflectionMethod->invoke(null, $originalConfig);
        $this->assertEquals($expectedConfig, $result);

        // Check that it doesn't attempt to add 'constructor' key if it's not already present
        $originalConfig = ['no_constructor_present' => 'foo'];
        $expectedConfig = ['no_constructor_present' => 'foo'];

        $result = $reflectionMethod->invoke(null, $originalConfig);
        $this->assertEquals($expectedConfig, $result);

        // Check that it doesn't overwrite an existing redirectUri
        $originalConfig = ['constructor' => ['Options' => ['apiKey' => '123', 'redirectUri' => 'http://foo.bar']]];
        $expectedConfig = ['constructor' => ['Options' => ['apiKey' => '123', 'redirectUri' => 'http://foo.bar']]];

        $result = $reflectionMethod->invoke(null, $originalConfig);
        $this->assertEquals($expectedConfig, $result);
    }

    public function testGetRedirectUri()
    {
        // Store originals
        $config = Config::inst();
        $injector = Injector::inst();

        // Setup reflection
        $reflectionMethod = new ReflectionMethod(
            'Bigfork\SilverStripeOAuth\Client\Helper\Helper',
            'getRedirectUri'
        );
        $reflectionMethod->setAccessible(true);

        $mockConfig = $this->getMock('Config', ['get']);
        $mockConfig->expects($this->once())
            ->method('get')
            ->with('Bigfork\SilverStripeOAuth\Client\Helper\Helper', 'default_redirect_uri')
            ->will($this->returnValue('http://foo.bar'));

        Config::set_instance($mockConfig);
        $this->assertEquals('http://foo.bar', $reflectionMethod->invoke(null));
        Config::set_instance($config);

        $mockInjector = $this->getMock('Injector', ['get']);
        $mockInjector->expects($this->once())
            ->method('get')
            ->with('Bigfork\SilverStripeOAuth\Client\Control\Controller')
            ->will($this->returnValue(new HelperTest_Controller()));

        Injector::set_inst($mockInjector);
        $this->assertEquals('http://mysite.com/helpertest/callback/', $reflectionMethod->invoke(null));
        Injector::set_inst($injector);
    }
}

class HelperTest_Controller extends Controller
{
    public function Link()
    {
        return 'helpertest/';
    }

    public function AbsoluteLink()
    {
        return 'http://mysite.com/helpertest/';
    }
}
