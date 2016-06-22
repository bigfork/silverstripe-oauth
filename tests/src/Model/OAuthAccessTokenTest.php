<?php

namespace Bigfork\SilverStripeOAuth\Client\Test\Model;

use Injector;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Token\AccessToken;
use OAuthAccessToken;
use SapphireTest;

class OAuthAccessTokenTest extends SapphireTest
{
    protected static $fixture_file = 'OAuthAccessTokenTest.yml';

    public function testCreateFromAccessToken()
    {
        $tokenData = [
            'access_token' => '123',
            'resource_owner_id' => '1',
            'refresh_token' => '987',
            'expires' => time()
        ];

        $leagueToken = new AccessToken($tokenData);

        $token = OAuthAccessToken::createFromAccessToken('provider_name', $leagueToken);
        $this->assertEquals('provider_name', $token->Provider);
        $this->assertEquals($tokenData['access_token'], $token->Token);
        $this->assertEquals($tokenData['resource_owner_id'], $token->ResourceOwnerID);
        $this->assertEquals($tokenData['refresh_token'], $token->RefreshToken);
        $this->assertEquals($tokenData['expires'], $token->Expires);

        // Test that the expiry date is translated to datetime field correctly on write
        $token->write();
        $token = OAuthAccessToken::get()->filter('Provider', 'provider_name')->first();
        $this->assertEquals(date('Y-m-d h:i:s'), $token->Expires, 'Expiry date was stored incorrectly');

        // Test expires_in instead of expires
        $oneDay = 60 * 60 * 24;
        $tokenData['expires_in'] = $oneDay; // 24 hours
        $leagueToken = new AccessToken($tokenData);
        $token = OAuthAccessToken::createFromAccessToken('new_provider_name', $leagueToken);
        $this->assertGreaterThan(time(), $token->Expires, 'Incorrect expiry date');
        $this->assertLessThan(time() + $oneDay + 1, $token->Expires, 'Incorrect expiry date');
    }

    public function testIsExpired()
    {
        $expiredToken = $this->objFromFixture('OAuthAccessToken', 'expired');
        $this->assertTrue($expiredToken->isExpired());
    }

    /**
     * @expectedException RuntimeException
     */
    public function testIsExpiredNotSet()
    {
        $tokenWithoutExpires = $this->objFromFixture('OAuthAccessToken', 'no_expiry');
        $this->assertFalse($tokenWithoutExpires->isExpired());
    }

    public function testRefresh()
    {
        $refreshToken = '123456789';
        $timeStamp = time();

        $mockAccessToken = $this->getMockBuilder('League\OAuth2\Client\Token\AccessToken')
            ->setMethods(['getToken', 'getRefreshToken', 'getExpires', 'getResourceOwnerId'])
            ->disableOriginalConstructor()
            ->getMock();
        $mockAccessToken->expects($this->once())
            ->method('getToken')
            ->will($this->returnValue('987'));
        $mockAccessToken->expects($this->once())
            ->method('getRefreshToken')
            ->will($this->returnValue('123'));
        $mockAccessToken->expects($this->once())
            ->method('getExpires')
            ->will($this->returnValue($timeStamp));
        $mockAccessToken->expects($this->once())
            ->method('getResourceOwnerId')
            ->will($this->returnValue('abc'));

        $mockProvider = $this->getMockBuilder('League\OAuth2\Client\Provider\GenericProvider')
            ->setMethods(['getAccessToken'])
            ->disableOriginalConstructor()
            ->getMock();
        $mockProvider->expects($this->once())
            ->method('getAccessToken')
            ->with('refresh_token', ['refresh_token' => $refreshToken])
            ->will($this->returnValue($mockAccessToken));

        $mockToken = $this->getMockBuilder('OAuthAccessToken')
            ->setMethods(['getTokenProvider'])
            ->getMock();
        $mockToken->expects($this->once())
            ->method('getTokenProvider')
            ->will($this->returnValue($mockProvider));

        $mockToken->RefreshToken = $refreshToken;
        $mockToken->refresh();

        $this->assertEquals('987', $mockToken->Token);
        $this->assertEquals('123', $mockToken->RefreshToken);
        $this->assertEquals($timeStamp, $mockToken->Expires);
        $this->assertEquals('abc', $mockToken->ResourceOwnerID);
    }

    public function testGetTokenProvider()
    {
        $mockProvider = $this->getMockBuilder('League\OAuth2\Client\Provider\GenericProvider')
            ->disableOriginalConstructor()
            ->getMock();

        $mockFactory = $this->getMockBuilder('Bigfork\SilverStripeOAuth\Client\Factory\ProviderFactory')
            ->setMethods(['createProvider'])
            ->getMock();
        $mockFactory->expects($this->once())
            ->method('createProvider')
            ->with('ProviderName')
            ->will($this->returnValue($mockProvider));

        Injector::inst()->registerService($mockFactory, 'Bigfork\SilverStripeOAuth\Client\Factory\ProviderFactory');

        $token = new OAuthAccessToken();
        $token->Provider = 'ProviderName';
        $this->assertSame($mockProvider, $token->getTokenProvider());
    }

    public function testIncludesScope()
    {
        $mockToken = $this->getMockBuilder('OAuthAccessToken')
            ->setMethods(['includesScopes'])
            ->getMock();
        $mockToken->expects($this->once())
            ->method('includesScopes')
            ->with(['test_scope'])
            ->will($this->returnValue(true));

        $this->assertTrue($mockToken->includesScope('test_scope'));
    }

    public function testIncludesScopes()
    {
        $token = $this->objFromFixture('OAuthAccessToken', 'valid');
        $this->assertTrue($token->includesScopes(['test_scope']));
        $this->assertTrue($token->includesScopes(['test_scope', 'another_test_scope']));
        $this->assertFalse($token->includesScopes(['test_scope', 'invalid_test_scope']));
        $this->assertFalse($token->includesScopes(['invalid_test_scope']));
    }
}
