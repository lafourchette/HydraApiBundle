<?php

namespace LaFourchette\HydraApiBundle\Authentication;

use CommerceGuys\Guzzle\Plugin\Oauth2\GrantType\PasswordCredentials;
use CommerceGuys\Guzzle\Plugin\Oauth2\Oauth2Plugin;
use Guzzle\Common\Event;
use Guzzle\Http\Client;
use Symfony\Component\HttpKernel\Log\LoggerInterface;

class Oauth2SimplePasswordAuthentication implements AuthenticationInterface
{
    /**
     * @var array
     */
    protected $options;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var Oauth2Plugin
     */
    protected $oauth2plugin;

    /**
     * @var PasswordCredentials
     */
    protected $passwordCredentials;

    /**
     * @var array
     */
    protected $oauthToken;

    /**
     * @param LoggerInterface       $logger
     * @param Oauth2Plugin          $oauth2plugin
     * @param PasswordCredentials   $passwordCredentials
     * @param array                 $options
     */
    public function __construct(
        LoggerInterface $logger,
        Oauth2Plugin $oauth2plugin,
        PasswordCredentials $passwordCredentials,
        array $options = array())
    {
        $this->logger = $logger;

        $this->oauth2plugin = $oauth2plugin;
        $this->passwordCredentials = $passwordCredentials;
        $this->options = $options;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'oauth2_simple_password';
    }

    /**
     * {@inheritdoc}
     *
     * @throws \RuntimeException
     */
    public function onRequestError(Event $event)
    {
        throw new \Exception('Error Processing Request', 1);
    }

    /**
     * @return string|null
     */
    public function getOauthAccessToken()
    {
        return isset($this->oauthToken['access_token']) ? $this->oauthToken['access_token'] : null;
    }

    /**
     * @return string|null
     */
    public function getOauthRefreshToken()
    {
        return isset($this->oauthToken['refresh_token']) ? $this->oauthToken['refresh_token'] : null;
    }

    /**
     * @return string|null
     */
    public function getOauthExpiresIn()
    {
        return isset($this->oauthToken['expires_in']) ? $this->oauthToken['expires_in'] : null;
    }

    /**
     * {@inheritdoc}
     */
    public function onRequestBeforeSend(Event $event)
    {
        $this->oauthToken = $this->passwordCredentials->getTokenData();

        $this->oauth2plugin->setAccessToken(array(
            'access_token' => $this->getOauthAccessToken(),
            'expires' => time() + $this->getOauthExpiresIn(),
        ));

        $this->oauth2plugin->onRequestBeforeSend($event);

        $this->logger->info('oauth2_simple_password authenticate');
    }
}
