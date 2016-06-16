<?php

namespace Bigfork\SilverStripeOAuth\Client\Control;

use Bigfork\SilverStripeOAuth\Client\Authenticator\Authenticator;
use Bigfork\SilverStripeOAuth\Client\Exception\TokenlessUserExistsException;
use Bigfork\SilverStripeOAuth\Client\Factory\ProviderFactory;
use Controller as SilverStripeController;
use Injector;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use Member;
use OAuthAccessToken;
use Session;
use Security;
use SS_HTTPRequest;

class Controller extends SilverStripeController
{
    /**
     * @var array
     */
    private static $allowed_actions = [
        'authenticate'
    ];

    /**
     * @param SS_HTTPRequest $request
     * @return mixed
     */
    public function authenticate(SS_HTTPRequest $request)
    {
        if (!$this->validateState($request)) {
            return Security::permissionFailure($this, 'Invalid session state.');
        }

        $providerName = Session::get('oauth2.provider');
        // @todo Use ProviderFactory::class in SS4 - it currently breaks SS_ConfigStaticManifest_Parser
        $provider = Injector::inst()->get('Bigfork\SilverStripeOAuth\Client\Factory\ProviderFactory')
            ->createProvider($providerName);
        
        try {
            $token = $provider->getAccessToken('authorization_code', [
                'code' => $request->getVar('code')
            ]);

            $user = $provider->getResourceOwner($token);
            $accessToken = OAuthAccessToken::createFromAccessToken($providerName, $token);

            $member = $this->memberFromResourceOwner($user, $providerName);
            $accessToken->MemberID = $member->ID;
            $accessToken->write();
        } catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
            return Security::permissionFailure($this, 'Invalid access token.');
        } catch (TokenlessUserExistsException $e) {
            return Security::permissionFailure($this, $e->getMessage());
        }

        $result = $member->canLogIn();
        if (!$result->valid()) {
            return Security::permissionFailure($this, $result->message());
        }

        $member->logIn();
        return $this->redirectBack();
    }

    /**
     * Find or create a member from the given resource owner ("user")
     *
     * @todo Implement $overwriteExisting
     * @todo $overwriteExisting could use priorities? I.e. Facebook data > Google data
     * @param ResourceOwnerInterface $user
     * @return Member
     * @throws TokenlessUserExistsException
     */
    protected function memberFromResourceOwner(ResourceOwnerInterface $user, $providerName)
    {
        $member = Member::get()->filter([
            'Email' => $user->getEmail()
        ])->first();

        if (!$member) {
            $member = Member::create();
        }

        if ($member->isInDB() && !$member->AccessTokens()->count()) {
            throw new TokenlessUserExistsException(
                'A user with the email address linked to this account already exists.'
            );
        }

        $overwriteExisting = false; // @todo
        if ($overwriteExisting || !$member->isInDB()) {
            $mapper = Injector::inst()->get('Bigfork\SilverStripeOAuth\Client\Factory\MemberMapperFactory')
                ->createMapper($providerName);

            $member = $mapper->map($member, $user);
            $member->write();
        }

        return $member;
    }

    /**
     * Validate the request's state against the one stored in session
     *
     * @param SS_HTTPRequest $request
     * @return boolean
     */
    protected function validateState(SS_HTTPRequest $request)
    {
        $state = $request->getVar('state');
        $sessionState = Session::get('oauth2.state');
        $sessionProvider = Session::get('oauth2.provider');

        // If we're lacking any required state data, or the session state
        // doesn't match the one given by the provider, it's not valid
        if (!$state || $state !== $sessionState || !$sessionProvider) {
            Session::clear('oauth2.state');
            Session::clear('oauth2.provider');
            return false;
        }

        return true;
    }
}
