<?php

namespace Bigfork\SilverStripeOAuth\Client\Test\Control;

use Bigfork\SilverStripeOAuth\Client\Control\Controller;
use Bigfork\SilverStripeOAuth\Client\Test\TestCase;
use Config;
use Director;
use Injector;
use OAuthAccessToken;
use ReflectionMethod;
use SS_HTTPRequest;
use SS_HTTPResponse;
use SS_HTTPResponse_Exception;

class ControllerTest extends TestCase
{
    public function testFindBackUrl()
    {
        $back = Director::absoluteBaseURL() . 'test/';
        $controller = new Controller;
        $reflectionMethod = new ReflectionMethod(
            'Bigfork\SilverStripeOAuth\Client\Control\Controller',
            'findBackUrl'
        );
        $reflectionMethod->setAccessible(true);

        $mockRequest = $this->getConstructorlessMock('SS_HTTPRequest', ['requestVar']);
        $mockRequest->expects($this->exactly(2))
            ->method('requestVar')
            ->with('BackURL')
            ->will($this->returnValue($back));
        $this->assertEquals($back, $reflectionMethod->invoke($controller, $mockRequest));

        $mockRequest = $this->getConstructorlessMock('SS_HTTPRequest', ['requestVar', 'isAjax', 'getHeader']);
        $mockRequest->expects($this->at(0))
            ->method('requestVar')
            ->with('BackURL')
            ->will($this->returnValue(null));
        $mockRequest->expects($this->at(1))
            ->method('isAjax')
            ->will($this->returnValue(true));
        $mockRequest->expects($this->at(2))
            ->method('getHeader')
            ->with('X-Backurl')
            ->will($this->returnValue($back));
        $mockRequest->expects($this->at(3))
            ->method('getHeader')
            ->with('X-Backurl')
            ->will($this->returnValue($back));
        $this->assertEquals($back, $reflectionMethod->invoke($controller, $mockRequest));

        $mockRequest = $this->getConstructorlessMock('SS_HTTPRequest', ['requestVar', 'isAjax', 'getHeader']);
        $mockRequest->expects($this->at(0))
            ->method('requestVar')
            ->with('BackURL')
            ->will($this->returnValue(null));
        $mockRequest->expects($this->at(1))
            ->method('isAjax')
            ->will($this->returnValue(false));
        $mockRequest->expects($this->at(2))
            ->method('getHeader')
            ->with('Referer')
            ->will($this->returnValue($back));
        $mockRequest->expects($this->at(3))
            ->method('getHeader')
            ->with('Referer')
            ->will($this->returnValue($back));
        $this->assertEquals($back, $reflectionMethod->invoke($controller, $mockRequest));

        $mockRequest = $this->getConstructorlessMock('SS_HTTPRequest', ['requestVar']);
        $mockRequest->expects($this->exactly(2))
            ->method('requestVar')
            ->with('BackURL')
            ->will($this->returnValue('http://1337h4x00r.com/geniune-oauth-url/i-promise'));
        $this->assertEquals(Director::absoluteBaseURL(), $reflectionMethod->invoke($controller, $mockRequest));
    }

    public function testGetReturnUrl()
    {
        $back = Director::absoluteBaseURL() . 'test/';
        $controller = new Controller;
        $reflectionMethod = new ReflectionMethod(
            'Bigfork\SilverStripeOAuth\Client\Control\Controller',
            'getReturnUrl'
        );
        $reflectionMethod->setAccessible(true);

        $mockSession = $this->getConstructorlessMock('Session', ['inst_get']);
        $mockSession->expects($this->once())
            ->method('inst_get')
            ->with('oauth2.backurl')
            ->will($this->returnValue($back));

        $controller->setSession($mockSession);
        $this->assertEquals($back, $reflectionMethod->invoke($controller));

        $mockSession = $this->getConstructorlessMock('Session', ['inst_get']);
        $mockSession->expects($this->once())
            ->method('inst_get')
            ->with('oauth2.backurl')
            ->will($this->returnValue('http://1337h4x00r.com/geniune-oauth-url/i-promise'));

        $controller->setSession($mockSession);
        $this->assertEquals(Director::absoluteBaseURL(), $reflectionMethod->invoke($controller));
    }

    public function testAuthenticate()
    {
        // Store original
        $injector = Injector::inst();

        $mockRequest = $this->getConstructorlessMock('SS_HTTPRequest', ['getVar']);
        $mockRequest->expects($this->at(0))
            ->method('getVar')
            ->with('provider')
            ->will($this->returnValue('ProviderName'));
        $mockRequest->expects($this->at(1))
            ->method('getVar')
            ->with('context')
            ->will($this->returnValue('testcontext'));
        $mockRequest->expects($this->at(2))
            ->method('getVar')
            ->with('scope')
            ->will($this->returnValue([])); // Leave scopes empty to assert that getDefaultScopes() is called

        $mockProvider = $this->getConstructorlessMock(
            'League\OAuth2\Client\Provider\GenericProvider',
            ['getDefaultScopes', 'getAuthorizationUrl', 'getState']
        );
        $mockProvider->expects($this->at(0))
            ->method('getDefaultScopes')
            ->will($this->returnValue(['default_scope']));
        $mockProvider->expects($this->at(1))
            ->method('getAuthorizationUrl')
            ->with(['scope' => ['default_scope']])
            ->will($this->returnValue('http://example.com/oauth'));
        $mockProvider->expects($this->at(2))
            ->method('getState')
            ->will($this->returnValue('mockstate'));

        $mockProviderFactory = $this->getMock(
            'Bigfork\SilverStripeOAuth\Client\Factory\ProviderFactory',
            ['getProvider']
        );
        $mockProviderFactory->expects($this->once())
            ->method('getProvider')
            ->with('ProviderName')
            ->will($this->returnValue($mockProvider));

        $mockSession = $this->getConstructorlessMock('Session', ['inst_set']);
        $mockSession->expects($this->once())
            ->method('inst_set')
            ->with('oauth2', [
                'state' => 'mockstate',
                'provider' => 'ProviderName',
                'context' => 'testcontext',
                'scope' => ['default_scope'],
                'backurl' => 'http://mysite.com/return'
            ]);

        $mockInjector = $this->getMock('Injector', ['get']);
        $mockInjector->expects($this->once())
            ->method('get')
            ->with('ProviderFactory')
            ->will($this->returnValue($mockProviderFactory));

        $mockController = $this->getMock(
            'Bigfork\SilverStripeOAuth\Client\Control\Controller',
            ['findBackUrl', 'redirect']
        );
        $mockController->expects($this->at(0))
            ->method('findBackUrl')
            ->with($mockRequest)
            ->will($this->returnValue('http://mysite.com/return'));
        $mockController->expects($this->at(1))
            ->method('redirect')
            ->with('http://example.com/oauth')
            ->will($this->returnValue($response = new SS_HTTPResponse));

        Injector::set_inst($mockInjector);

        $mockController->setSession($mockSession);
        $this->assertSame($response, $mockController->authenticate($mockRequest));

        // Restore things
        Injector::set_inst($injector);
    }

    public function testAuthenticateMissingRequiredData()
    {
        $mockRequest = $this->getConstructorlessMock('SS_HTTPRequest', ['getVar']);
        $mockRequest->expects($this->at(0))
            ->method('getVar')
            ->with('provider')
            ->will($this->returnValue(null));
        $mockRequest->expects($this->at(1))
            ->method('getVar')
            ->with('context')
            ->will($this->returnValue(null));
        $mockRequest->expects($this->at(2))
            ->method('getVar')
            ->with('scope')
            ->will($this->returnValue(null));

        $controller = new Controller();
        try {
            $response = $controller->authenticate($mockRequest);
            $this->fail('SS_HTTPResponse_Exception was not thrown');
        } catch (SS_HTTPResponse_Exception $e) {
            $this->assertEquals(404, $e->getResponse()->getStatusCode());
        }
    }

    public function testCallback()
    {
        // Store original
        $injector = Injector::inst();

        $mockRequest = $this->getConstructorlessMock('SS_HTTPRequest', ['getVar']);
        $mockRequest->expects($this->once())
            ->method('getVar')
            ->with('code')
            ->will($this->returnValue('12345'));

        $mockAccessToken = $this->getConstructorlessMock('League\OAuth2\Client\Token\AccessToken');

        $mockProvider = $this->getConstructorlessMock(
            'League\OAuth2\Client\Provider\GenericProvider',
            ['getAccessToken']
        );
        $mockProvider->expects($this->once())
            ->method('getAccessToken')
            ->with('authorization_code', ['code' => '12345'])
            ->will($this->returnValue($mockAccessToken));

        $mockProviderFactory = $this->getMock(
            'Bigfork\SilverStripeOAuth\Client\Factory\ProviderFactory',
            ['getProvider']
        );
        $mockProviderFactory->expects($this->once())
            ->method('getProvider')
            ->with('ProviderName')
            ->will($this->returnValue($mockProvider));

        $mockTokenHandler = $this->getMock(
            'Bigfork\SilverStripeOAuth\Client\Handler\TokenHandler',
            ['handleToken']
        );
        $mockTokenHandler->expects($this->once())
            ->method('handleToken')
            ->with($mockAccessToken, $mockProvider);

        $mockInjector = $this->getMock('Injector', ['get', 'create']);
        $mockInjector->expects($this->at(0))
            ->method('get')
            ->with('ProviderFactory')
            ->will($this->returnValue($mockProviderFactory));
        $mockInjector->expects($this->at(1))
            ->method('create')
            ->with('TestTokenHandler')
            ->will($this->returnValue($mockTokenHandler));

        $mockSession = $this->getConstructorlessMock('Session', ['inst_get', 'inst_clear']);
        $mockSession->expects($this->at(0))
            ->method('inst_get')
            ->with('oauth2.provider')
            ->will($this->returnValue('ProviderName'));
        $mockSession->expects($this->at(1))
            ->method('inst_get')
            ->with('oauth2.context')
            ->will($this->returnValue('testcontext'));
        $mockSession->expects($this->at(2))
            ->method('inst_clear')
            ->with('oauth2');

        $mockController = $this->getMock(
            'Bigfork\SilverStripeOAuth\Client\Control\Controller',
            ['getSession', 'validateState', 'getHandlersForContext', 'getReturnUrl', 'redirect']
        );
        $mockController->expects($this->at(0))
            ->method('getSession')
            ->will($this->returnValue($mockSession));
        $mockController->expects($this->at(1))
            ->method('validateState')
            ->with($mockRequest)
            ->will($this->returnValue(true));
        $mockController->expects($this->at(2))
            ->method('getHandlersForContext')
            ->with('testcontext')
            ->will($this->returnValue([['priority' => 1, 'context' => 'testcontext', 'class' => 'TestTokenHandler']]));
        $mockController->expects($this->at(3))
            ->method('getReturnUrl')
            ->will($this->returnValue('http://mysite.com/return'));
        $mockController->expects($this->at(4))
            ->method('redirect')
            ->with('http://mysite.com/return')
            ->will($this->returnValue($response = new SS_HTTPResponse));

        Injector::set_inst($mockInjector);

        $mockController->setSession($mockSession);
        $this->assertSame($response, $mockController->callback($mockRequest));

        // Restore things
        Injector::set_inst($injector);
    }

    public function testCallbackInvalidState()
    {
        $mockRequest = $this->getConstructorlessMock('SS_HTTPRequest');

        $mockSession = $this->getConstructorlessMock('Session', ['inst_clear']);
        $mockSession->expects($this->once())
            ->method('inst_clear')
            ->with('oauth2');

        $mockController = $this->getMock(
            'Bigfork\SilverStripeOAuth\Client\Control\Controller',
            ['getSession', 'validateState']
        );
        $mockController->expects($this->at(0))
            ->method('getSession')
            ->will($this->returnValue($mockSession));
        $mockController->expects($this->at(1))
            ->method('validateState')
            ->with($mockRequest)
            ->will($this->returnValue(false));

        try {
            $response = $mockController->callback($mockRequest);
            $this->fail('SS_HTTPResponse_Exception was not thrown');
        } catch (SS_HTTPResponse_Exception $e) {
            $this->assertEquals(400, $e->getResponse()->getStatusCode());
            $this->assertEquals('Invalid session state.', $e->getResponse()->getBody());
        }
    }

    /**
     * @expectedException Exception
     */
    public function testGetHandlersForContextWithNoHandlers()
    {
        Config::inst()->remove('Bigfork\SilverStripeOAuth\Client\Control\Controller', 'token_handlers');
        Config::inst()->update('Bigfork\SilverStripeOAuth\Client\Control\Controller', 'token_handlers', []);

        $controller = new Controller;
        $reflectionMethod = new ReflectionMethod(
            'Bigfork\SilverStripeOAuth\Client\Control\Controller',
            'getHandlersForContext'
        );
        $reflectionMethod->setAccessible(true);
        $reflectionMethod->invoke($controller);
    }

    public function testGetHandlersForContext()
    {
        Config::inst()->remove('Bigfork\SilverStripeOAuth\Client\Control\Controller', 'token_handlers');
        Config::inst()->update('Bigfork\SilverStripeOAuth\Client\Control\Controller', 'token_handlers', [
            'globalhandlertwo' => [
                'priority' => 3, 'context' => '*', 'class' => 'GlobalHandlerTwo'
            ],
            'namedhandlerone' => [
                'priority' => 1, 'context' => 'testcontext', 'class' => 'NamedHandlerOne'
            ],
            'globalhandlerone' => [
                'priority' => 2, 'context' => '*', 'class' => 'GlobalHandlerOne'
            ],
            'namedhandlertwo' => [
                'priority' => 1, 'context' => 'anothertestcontext', 'class' => 'NamedHandlerTwo'
            ]
        ]);

        $controller = new Controller;
        $reflectionMethod = new ReflectionMethod(
            'Bigfork\SilverStripeOAuth\Client\Control\Controller',
            'getHandlersForContext'
        );
        $reflectionMethod->setAccessible(true);

        // Not giving a context should run all "global" handlers, but no named ones
        $expected = [
            'globalhandlerone' => [
                'priority' => 2, 'context' => '*', 'class' => 'GlobalHandlerOne'
            ],
            'globalhandlertwo' => [
                'priority' => 3, 'context' => '*', 'class' => 'GlobalHandlerTwo'
            ]
        ];
        $this->assertEquals($expected, $reflectionMethod->invoke($controller));

        // Passing a context should run all "global" handlers, and those that match the context
        $expected = [
            'namedhandlerone' => [
                'priority' => 1, 'context' => 'testcontext', 'class' => 'NamedHandlerOne'
            ],
            'globalhandlerone' => [
                'priority' => 2, 'context' => '*', 'class' => 'GlobalHandlerOne'
            ],
            'globalhandlertwo' => [
                'priority' => 3, 'context' => '*', 'class' => 'GlobalHandlerTwo'
            ]
        ];
        $this->assertEquals($expected, $reflectionMethod->invoke($controller, 'testcontext'));
    }
}
