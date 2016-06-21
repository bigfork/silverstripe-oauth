<?php

/**
 * A scope (or permission) for an access token. Note that scopes are not unique to each provider
 * (e.g. Facebook 'email' and Google 'email' scopes will use the same database record)
 */
class OAuthScope extends DataObject
{
    /**
     * @var array
     */
    private static $db = [
        'Name' => 'Varchar(255)'
    ];

    /**
     * @var array
     */
    private static $belongs_many_many = [
        'AccessTokens' => 'OAuthAccessToken'
    ];

    /**
     * Find a scope with the given name, or create one
     *
     * @param string $name
     * @return self
     */
    public static function findOrMake($name)
    {
        $scope = static::get()->filter('Name', $name)->first();

        if (!$scope) {
            $scope = static::create()->update(['Name' => $name]);
            $scope->write();
        }

        return $scope;
    }
}
