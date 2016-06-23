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

    /**
     * Remove all access tokens from the given provider
     *
     * @param string $provider
     */
    public function clearTokensFromProvider($provider)
    {
        $existingTokens = $this->owner->AccessTokens()->filter(['Provider' => $provider]);

        if ($existingTokens->count()) {
            $existingTokens->removeAll();
        }
    }
}
