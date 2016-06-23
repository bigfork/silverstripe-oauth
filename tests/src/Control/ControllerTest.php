<?php

namespace Bigfork\SilverStripeOAuth\Client\Test\Control;

use Bigfork\SilverStripeOAuth\Client\Control\Controller;
use Director;
use Injector;
use Member;
use ReflectionMethod;
use SapphireTest;
use SS_HTTPRequest;
use SS_HTTPResponse;
use SS_HTTPResponse_Exception;

class ControllerTest extends SapphireTest
{
    public function testSetGetMember()
    {
        $controller = new Controller;

        $this->assertEquals(Member::currentUser(), $controller->getMember());

        $member = new Member;
        $this->assertSame($controller, $controller->setMember($member));
        $this->assertSame($member, $controller->getMember());
    }

    public function testFindBackUrl()
    {
        $back = Director::absoluteBaseURL() . 'test/';
        $controller = new Controller;
        $reflectionMethod = new ReflectionMethod(
            'Bigfork\SilverStripeOAuth\Client\Control\Controller',
            'findBackUrl'
        );
        $reflectionMethod->setAccessible(true);

        $mockBuilder = $this->getMockBuilder('SS_HTTPRequest')->disableOriginalConstructor();

        $mock = $mockBuilder->setMethods(['requestVar'])->getMock();
        $mock->expects($this->exactly(2))
            ->method('requestVar')
            ->with('BackURL')
            ->will($this->returnValue($back));
        $this->assertEquals($back, $reflectionMethod->invoke($controller, $mock));

        $mock = $mockBuilder->setMethods(['requestVar', 'isAjax', 'getHeader'])->getMock();
        $mock->expects($this->at(0))
            ->method('requestVar')
            ->with('BackURL')
            ->will($this->returnValue(null));
        $mock->expects($this->at(1))
            ->method('isAjax')
            ->will($this->returnValue(true));
        $mock->expects($this->at(2))
            ->method('getHeader')
            ->with('X-Backurl')
            ->will($this->returnValue($back));
        $mock->expects($this->at(3))
            ->method('getHeader')
            ->with('X-Backurl')
            ->will($this->returnValue($back));
        $this->assertEquals($back, $reflectionMethod->invoke($controller, $mock));

        $mock = $mockBuilder->setMethods(['requestVar', 'isAjax', 'getHeader'])->getMock();
        $mock->expects($this->at(0))
            ->method('requestVar')
            ->with('BackURL')
            ->will($this->returnValue(null));
        $mock->expects($this->at(1))
            ->method('isAjax')
            ->will($this->returnValue(false));
        $mock->expects($this->at(2))
            ->method('getHeader')
            ->with('Referer')
            ->will($this->returnValue($back));
        $mock->expects($this->at(3))
            ->method('getHeader')
            ->with('Referer')
            ->will($this->returnValue($back));
        $this->assertEquals($back, $reflectionMethod->invoke($controller, $mock));

        $mock = $mockBuilder->setMethods(['requestVar'])->getMock();
        $mock->expects($this->exactly(2))
            ->method('requestVar')
            ->with('BackURL')
            ->will($this->returnValue('http://1337h4x00r.com/geniune-oauth-url/i-promise'));
        $this->assertEquals(Director::absoluteBaseURL(), $reflectionMethod->invoke($controller, $mock));
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

        $mockSession = $this->getMockBuilder('Session')
            ->disableOriginalConstructor()
            ->setMethods(['inst_get'])
            ->getMock();
        $mockSession->expects($this->once())
            ->method('inst_get')
            ->with('oauth2.backurl')
            ->will($this->returnValue($back));

        $controller->setSession($mockSession);
        $this->assertEquals($back, $reflectionMethod->invoke($controller));

        $mockSession = $this->getMockBuilder('Session')
            ->disableOriginalConstructor()
            ->setMethods(['inst_get'])
            ->getMock();
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

        $mockRequest = $this->getMockBuilder('SS_HTTPRequest')
            ->disableOriginalConstructor()
            ->setMethods(['getVar'])
            ->getMock();
        $mockRequest->expects($this->at(0))
            ->method('getVar')
            ->with('provider')
            ->will($this->returnValue('ProviderName'));
        $mockRequest->expects($this->at(1))
            ->method('getVar')
            ->with('scope')
            ->will($this->returnValue([])); // Leave scopes empty to asset that getDefaultScopes() is called

        $mockProvider = $this->getMockBuilder('League\OAuth2\Client\Provider\GenericProvider')
            ->disableOriginalConstructor()
            ->setMethods(['getDefaultScopes', 'getAuthorizationUrl', 'getState'])
            ->getMock();
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

        $mockProviderFactory = $this->getMockBuilder('Bigfork\SilverStripeOAuth\Client\Factory\ProviderFactory')
            ->setMethods(['getProvider'])
            ->getMock();
        $mockProviderFactory->expects($this->once())
            ->method('getProvider')
            ->with('ProviderName')
            ->will($this->returnValue($mockProvider));

        $mockSession = $this->getMockBuilder('Session')
            ->disableOriginalConstructor()
            ->setMethods(['inst_set'])
            ->getMock();
        $mockSession->expects($this->once())
            ->method('inst_set')
            ->with('oauth2', [
                'state' => 'mockstate',
                'provider' => 'ProviderName',
                'scope' => ['default_scope'],
                'backurl' => 'http://mysite.com/return'
            ]);

        $mockInjector = $this->getMockBuilder('Injector')
            ->setMethods(['get'])
            ->getMock();
        $mockInjector->expects($this->once())
            ->method('get')
            ->with('ProviderFactory')
            ->will($this->returnValue($mockProviderFactory));

        $mockController = $this->getMockBuilder('Bigfork\SilverStripeOAuth\Client\Control\Controller')
            ->setMethods(['findBackUrl', 'redirect'])
            ->getMock();
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
        $mockRequest = $this->getMockBuilder('SS_HTTPRequest')
            ->disableOriginalConstructor()
            ->setMethods(['getVar'])
            ->getMock();
        $mockRequest->expects($this->at(0))
            ->method('getVar')
            ->with('provider')
            ->will($this->returnValue(null));
        $mockRequest->expects($this->at(1))
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

        $mockRequest = $this->getMockBuilder('SS_HTTPRequest')
            ->disableOriginalConstructor()
            ->setMethods(['getVar'])
            ->getMock();
        $mockRequest->expects($this->once())
            ->method('getVar')
            ->with('code')
            ->will($this->returnValue('12345'));

        $mockAccessToken = $this->getMockBuilder('League\OAuth2\Client\Token\AccessToken')
            ->disableOriginalConstructor()
            ->getMock();

        $mockProvider = $this->getMockBuilder('League\OAuth2\Client\Provider\GenericProvider')
            ->disableOriginalConstructor()
            ->setMethods(['getAccessToken'])
            ->getMock();
        $mockProvider->expects($this->once())
            ->method('getAccessToken')
            ->with('authorization_code', ['code' => '12345'])
            ->will($this->returnValue($mockAccessToken));

        $mockProviderFactory = $this->getMockBuilder('Bigfork\SilverStripeOAuth\Client\Factory\ProviderFactory')
            ->setMethods(['getProvider'])
            ->getMock();
        $mockProviderFactory->expects($this->once())
            ->method('getProvider')
            ->with('ProviderName')
            ->will($this->returnValue($mockProvider));

        $mockInjector = $this->getMockBuilder('Injector')
            ->setMethods(['get'])
            ->getMock();
        $mockInjector->expects($this->once())
            ->method('get')
            ->with('ProviderFactory')
            ->will($this->returnValue($mockProviderFactory));

        $mockSession = $this->getMockBuilder('Session')
            ->disableOriginalConstructor()
            ->setMethods(['inst_get'])
            ->getMock();
        $mockSession->expects($this->once())
            ->method('inst_get')
            ->with('oauth2.provider')
            ->will($this->returnValue('ProviderName'));

        $mockMember = $this->getMockBuilder('Member')
            ->setMethods(['clearTokensFromProvider'])
            ->getMock();
        $mockMember->expects($this->once())
            ->method('clearTokensFromProvider')
            ->with('ProviderName');

        $mockController = $this->getMockBuilder('Bigfork\SilverStripeOAuth\Client\Control\Controller')
            ->setMethods(['validateState', 'getSession', 'extend', 'getMember',
                'storeAccessToken', 'getReturnUrl', 'redirect'])
            ->getMock();
        $mockController->expects($this->at(0))
            ->method('validateState')
            ->with($mockRequest)
            ->will($this->returnValue(true));
        $mockController->expects($this->at(1))
            ->method('getSession')
            ->will($this->returnValue($mockSession));
        $mockController->expects($this->at(2))
            ->method('extend')
            ->with('afterGetAccessToken', $mockProvider, $mockAccessToken, 'ProviderName', $mockRequest)
            ->will($this->returnValue([]));
        $mockController->expects($this->at(3))
            ->method('getMember')
            ->will($this->returnValue($mockMember));
        $mockController->expects($this->at(4))
            ->method('storeAccessToken')
            ->with($mockMember, $mockAccessToken, 'ProviderName');
        $mockController->expects($this->at(5))
            ->method('getReturnUrl')
            ->will($this->returnValue('http://mysite.com/return'));
        $mockController->expects($this->at(6))
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
        $mockRequest = $this->getMockBuilder('SS_HTTPRequest')
            ->disableOriginalConstructor()
            ->getMock();

        $mockController = $this->getMockBuilder('Bigfork\SilverStripeOAuth\Client\Control\Controller')
            ->setMethods(['validateState'])
            ->getMock();
        $mockController->expects($this->once())
            ->method('validateState')
            ->with($mockRequest)
            ->will($this->returnValue(false));

        $controller = new Controller();
        try {
            $response = $mockController->callback($mockRequest);
            $this->fail('SS_HTTPResponse_Exception was not thrown');
        } catch (SS_HTTPResponse_Exception $e) {
            $this->assertEquals(400, $e->getResponse()->getStatusCode());
            $this->assertEquals('Invalid session state.', $e->getResponse()->getBody());
        }
    }
}
