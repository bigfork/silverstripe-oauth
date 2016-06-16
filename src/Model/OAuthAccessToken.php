<?php

use League\OAuth2\Client\Token\AccessToken;
use Bigfork\SilverStripeOAuth\Client\Factory\ProviderFactory;

class OAuthAccessToken extends DataObject
{
    /**
     * @var array
     */
    private static $db = [
        'Provider' => 'Varchar(255)',
        'Token' => 'Text',
        'RefreshToken' => 'Text',
        'Expires' => 'SS_DateTime',
        'ResourceOwnerID' => 'Varchar(255)'
    ];

    /**
     * @var array
     */
    private static $has_one = [
        'Member' => 'Member'
    ];

    /**
     * Find a token matching the given parameters, or create one if it doesn't exist
     *
     * @param string $provider
     * @param AccessToken $token
     * @return self
     */
    public static function createFromAccessToken($provider, AccessToken $token)
    {
        $data = [
            'Provider' => $provider,
            'Token' => $token->getToken(),
            'RefreshToken' => $token->getRefreshToken(),
            'Expires' => $token->getExpires(),
            'ResourceOwnerID' => $token->getResourceOwnerId()
        ];

        $token = static::create()->update($data);

        return $token;
    }

    /**
     * @return boolean
     */
    public function isExpired()
    {
        $expires = $this->dbObject('Expires');

        if (!$expires->getValue()) {
            throw new RuntimeException('"expires" is not set on the token');
        }

        return $expires->InPast();
    }

    /**
     * Refresh this access token
     *
     * @return self
     */
    public function refresh()
    {
        $provider = $this->getTokenProvider();
        $newToken = $provider->getAccessToken('refresh_token', [
            'refresh_token' => $this->RefreshToken
        ]);

        $this->update([
            'Token' => $newToken->getToken(),
            'RefreshToken' => $newToken->getRefreshToken(),
            'Expires' => $newToken->getExpires(),
            'ResourceOwnerID' => $newToken->getResourceOwnerId()
        ])->write();

        return $this;
    }

    /**
     * @return League\OAuth2\Client\Provider\AbstractProvider
     */
    public function getTokenProvider()
    {
        // @todo Use ProviderFactory::class in SS4 - it currently breaks SS_ConfigStaticManifest_Parser
        return Injector::inst()->get('Bigfork\SilverStripeOAuth\Client\Factory\ProviderFactory')
            ->createProvider($this->Provider);
    }
}
