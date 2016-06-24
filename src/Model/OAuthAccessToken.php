<?php

use Bigfork\SilverStripeOAuth\Client\Factory\ProviderFactory;
use League\OAuth2\Client\Token\AccessToken;

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
     * @var array
     */
    private static $many_many = [
        'Scopes' => 'OAuthScope'
    ];

    /**
     * @var array
     */
    private static $summary_fields = [
        'Provider' => 'Provider',
        'Expires' => 'Expires'
    ];

    /**
     * {@inheritdoc}
     */
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $fields->removeByName('MemberID');
        $fields->removeByName('Scopes');

        $fields = $fields->transform(new ReadonlyTransformation());

        $fields->addFieldToTab(
            'Root.Main',
            GridField::create(
                'Scopes',
                'Scopes',
                $this->Scopes(),
                new GridFieldConfig_Base()
            )
        );

        return $fields;
    }

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
        return Injector::inst()->get('Bigfork\SilverStripeOAuth\Client\Factory\ProviderFactory')
            ->getProvider($this->Provider);
    }

    /**
     * Check whether this token's permissions include the given scope
     * 
     * @param string $scope
     * @return boolean
     */
    public function includesScope($scope)
    {
        return $this->includesScopes([$scope]);
    }

    /**
     * Check whether this token's permissions include all of the given scopes
     * NOTE: this is an *and*, not an *or*
     * 
     * @param array $scopes
     * @return boolean
     */
    public function includesScopes(array $scopes)
    {
        return ($this->Scopes()->filter('Name', $scopes)->count() === count($scopes));
    }
}
