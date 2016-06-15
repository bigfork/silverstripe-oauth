<?php

namespace Bigfork\SilverStripeOAuth\Client\Extension;

class MemberExtension extends \Extension
{
    /**
     * @var array
     */
    private static $has_many = [
        'AccessTokens' => 'OAuthAccessToken'
    ];

    /**
     * Remove this member's access tokens on delete
     */
    public function onBeforeDelete()
    {
        $this->owner->AccessTokens()->removeAll();
    }
}
