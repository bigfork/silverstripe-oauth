<?php

namespace Bigfork\SilverStripeOAuth\Client\Form;

use Bigfork\SilverStripeOAuth\Client\Authenticator\Authenticator;
use Bigfork\SilverStripeOAuth\Client\Factory\ProviderFactory;
use Config;
use FieldList;
use FormAction;
use HiddenField;
use Injector;
use LoginForm as SilverStripeLoginForm;
use Session;

class LoginForm extends SilverStripeLoginForm
{
    /**
     * @todo Use Authenticator::class in SS4 - it currently breaks SS_ConfigStaticManifest_Parser
     * @var string
     */
    protected $authenticator_class = 'Bigfork\SilverStripeOAuth\Client\Authenticator\Authenticator';

    /**
     * {@inheritdoc}
     */
    public function __construct($controller, $name)
    {
        parent::__construct($controller, $name, $this->getFields(), $this->getActions());
        $this->setHTMLID('OAuthAuthenticator');
    }

    /**
     * @return FieldList
     */
    public function getFields()
    {
        return FieldList::create(
            HiddenField::create('AuthenticationMethod', null, $this->authenticator_class, $this)
        );
    }

    /**
     * @todo Support for custom templates
     * @return FieldList
     */
    public function getActions()
    {
        $actions = FieldList::create();
        $providers = Config::inst()->get($this->authenticator_class, 'providers');

        foreach ($providers as $provider => $config) {
            $name = isset($config['name']) ? $config['name'] : $provider;
            $action = FormAction::create('authenticate_' . $provider, 'Sign in with ' . $name)
                ->setUseButtonTag(true);

            $actions->push($action);
        }

        return $actions;
    }

    /**
     * Handle a submission for a given provider - build redirection
     *
     * @todo Collaborators support?
     * @param string $name
     * @return SS_HTTPResponse
     */
    public function handleProvider($name)
    {
        // @todo Use ProviderFactory::class in SS4 - it currently breaks SS_ConfigStaticManifest_Parser
        $provider = Injector::inst()->get('Bigfork\SilverStripeOAuth\Client\Factory\ProviderFactory')
            ->createProvider($name);

        $providers = Config::inst()->get($this->authenticator_class, 'providers');
        $config = $providers[$name];
        $authorizationOptions = isset($config['authorization_options']) ? $config['authorization_options'] : [];

        $url = $provider->getAuthorizationUrl($authorizationOptions);
        Session::set('oauth2.state', $provider->getState());
        Session::set('oauth2.provider', $name);

        return $this->getController()->redirect($url);
    }

    /**
     * {@inheritdoc}
     */
    public function hasMethod($method)
    {
        if (strpos($method, 'authenticate_') === 0) {
            $providers = Config::inst()->get($this->authenticator_class, 'providers');
            $name = substr($method, strlen('authenticate_'));

            if (isset($providers[$name])) {
                return true;
            }
        }

        return parent::hasMethod($method);
    }

    /**
     * {@inheritdoc}
     */
    public function __call($method, $args)
    {
        if (strpos($method, 'authenticate_') === 0) {
            $providers = Config::inst()->get($this->authenticator_class, 'providers');
            $name = substr($method, strlen('authenticate_'));

            if (isset($providers[$name])) {
                return $this->handleProvider($name);
            }
        }

        return parent::__call($method, $args);
    }
}
