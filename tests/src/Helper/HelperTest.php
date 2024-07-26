<?php

namespace Bigfork\SilverStripeOAuth\Client\Test\Helper;

use Bigfork\SilverStripeOAuth\Client\Control\Controller;
use Bigfork\SilverStripeOAuth\Client\Factory\ProviderFactory;
use Bigfork\SilverStripeOAuth\Client\Helper\Helper;
use Bigfork\SilverStripeOAuth\Client\Test\TestCase;
use ReflectionMethod;
use SilverStripe\Config\Collections\CachedConfigCollection;
use SilverStripe\Config\Collections\MemoryConfigCollection;
use SilverStripe\Control\Controller as SilverStripeController;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\ConfigLoader;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Injector\InjectorLoader;

class HelperTest extends TestCase
{
    public function testBuildAuthorisationUrl()
    {
        $controller = new HelperTest_Controller;
        Injector::inst()->registerService($controller, Controller::class);

        $url = Helper::buildAuthorisationUrl('ProviderName', 'testcontext', ['test_scope']);

        $expected = Director::absoluteBaseURL() . 'helpertest/authenticate/';
        $expected .= '?provider=ProviderName&context=testcontext&scope%5B0%5D=test_scope';

        $this->assertEquals($expected, $url);
    }

    public function testAddRedirectUriToConfigs()
    {
        $uri = 'http://mysite.com/endpoint';

        $factoryConfig = ['properties' => ['providers' => ['ProviderName' => '%$ProviderService']]];
        $originalConfig = ['constructor' => ['Options' => ['apiKey' => '123']]];
        $expectedConfig = ['constructor' => ['Options' => ['apiKey' => '123', 'redirectUri' => $uri]]];

        $mockConfig = $this->getMockBuilder(CachedConfigCollection::class)
            ->setMethods(['get', 'nest'])
            ->getMock();
        $mockMutableConfig = $this->getMockBuilder(MemoryConfigCollection::class)
            ->setMethods(['set'])
            ->getMock();

        $mockConfig->expects($this->at(0))
            ->method('get')
            ->with(Injector::class, ProviderFactory::class)
            ->will($this->returnValue($factoryConfig));
        $mockConfig->expects($this->at(1))
            ->method('get')
            ->with(Injector::class, 'ProviderService')
            ->will($this->returnValue($originalConfig));
        // This smells, because it makes assumptions about methods other than the one we're testing.
        // But mocking static methods sucks.
        $mockConfig->expects($this->at(2))
            ->method('get')
            ->with(Helper::class, 'default_redirect_uri')
            ->will($this->returnValue($uri));
        $mockConfig->expects($this->at(3))
            ->method('nest')
            ->will($this->returnValue($mockMutableConfig));

        $mockMutableConfig->expects($this->once())
            ->method('set')
            ->with(Injector::class, 'ProviderService')
            ->will($this->returnValue($expectedConfig));

        $mockInjector = $this->getMockBuilder(Injector::class)
            ->setMethods(['load'])
            ->getMock();
        $mockInjector->expects($this->once())
            ->method('load')
            ->with(['ProviderService' => $expectedConfig]);

        // Inject mocks
        ConfigLoader::inst()->pushManifest($mockConfig);
        InjectorLoader::inst()->pushManifest($mockInjector);

        // Run the test
        Helper::addRedirectUriToConfigs();

        // Restore things
        ConfigLoader::inst()->popManifest();
        InjectorLoader::inst()->popManifest();
    }

    public function testAddRedirectUriToServiceConfig()
    {
        $uri = 'http://mysite.com/endpoint';
        Config::modify()->set(Helper::class, 'default_redirect_uri', $uri);

        // This assertion smells, because it makes assumptions about methods other than the one we're testing.
        // But mocking static methods sucks.
        $originalConfig = ['constructor' => ['Options' => ['apiKey' => '123']]];
        $expectedConfig = ['constructor' => ['Options' => ['apiKey' => '123', 'redirectUri' => $uri]]];

        $reflectionMethod = new ReflectionMethod(Helper::class, 'addRedirectUriToServiceConfig');
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
        // Setup reflection
        $reflectionMethod = new ReflectionMethod(Helper::class, 'getRedirectUri');
        $reflectionMethod->setAccessible(true);

        $mockConfig = $this->getMockBuilder(CachedConfigCollection::class)
            ->setMethods(['get'])
            ->getMock();
        $mockConfig->expects($this->once())
            ->method('get')
            ->with(Helper::class, 'default_redirect_uri')
            ->will($this->returnValue('http://foo.bar'));

        ConfigLoader::inst()->pushManifest($mockConfig);
        $this->assertEquals('http://foo.bar', $reflectionMethod->invoke(null));
        ConfigLoader::inst()->popManifest();

        $mockInjector = $this->getMockBuilder(Injector::class)
            ->setMethods(['get'])
            ->getMock();
        $mockInjector->expects($this->once())
            ->method('get')
            ->with(Controller::class)
            ->will($this->returnValue(new HelperTest_Controller()));

        InjectorLoader::inst()->pushManifest($mockInjector);
        $this->assertEquals('http://mysite.com/helpertest/callback/', $reflectionMethod->invoke(null));
        InjectorLoader::inst()->popManifest();
    }
}

class HelperTest_Controller extends SilverStripeController
{
    private static $url_segment = 'helpertest';

    public function AbsoluteLink($action = '')
    {
        return 'http://mysite.com/helpertest/';
    }
}
