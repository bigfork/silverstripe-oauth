<?php

namespace Bigfork\SilverStripeOAuth\Client\Test\Model;

use OAuthScope;
use SapphireTest;

class OAuthScopeTest extends SapphireTest
{
    public function testFindOrMake()
    {
        $testScopeName = 'test_scope_name';
        $matchingScopes = OAuthScope::get()->filter('Name', $testScopeName);

        $this->assertEquals(0, $matchingScopes->count());

        $scope = OAuthScope::findOrMake($testScopeName);
        $this->assertInstanceOf('OAuthScope', $scope, 'Scope was not created');
        $this->assertTrue($scope->isInDB(), 'Scope was not written to database');
        $this->assertEquals(1, $matchingScopes->count(), 'Newly written scope could not be found');
    }
}
