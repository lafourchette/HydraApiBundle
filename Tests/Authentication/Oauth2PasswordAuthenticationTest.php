<?php

namespace LaFourchette\HydraApiBundle\Tests\Authentication;

use Guzzle\Common\Event;
use Guzzle\Http\Client;
use Guzzle\Http\Message\Request;
use LaFourchette\HydraApiBundle\Authentication\Oauth2PasswordAuthentication;
use LaFourchette\HydraApiBundle\Business\TokenBusinessInterface;
use LaFourchette\HydraApiBundle\Entity\TokenInterface;
use LaFourchette\HydraApiBundle\Event\RefreshTokenEvent;
use LaFourchette\HydraApiBundle\GrantType\RefreshToken;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\Log\LoggerInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\SecurityContext;

class Oauth2PasswordAuthenticationTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var TokenBusinessInterface|ObjectProphecy
     */
    private $tokenBusinessMock;

    /**
     * @var LoggerInterface|ObjectProphecy
     */
    private $loggerMock;

    /**
     * @var SecurityContext|ObjectProphecy
     */
    private $securityContextMock;

    /**
     * @var mixed
     */
    private $userMock;

    /**
     * @var Oauth2PasswordAuthentication
     */
    private $oauth2PasswordAuthentication;

    public function setUp()
    {
        parent::setUp();

        $this->securityContextMock = $this->getSecurityContextMock();
        $this->tokenBusinessMock = $this->getTokenBusinessInterfaceMock();
        $this->loggerMock = $this->getLoggerMock();

        $this->oauth2PasswordAuthentication = new Oauth2PasswordAuthentication(
            $this->loggerMock->reveal(),
            $this->tokenBusinessMock->reveal(),
            $this->securityContextMock->reveal(),
            array('client_id' => 12345, 'client_secret' => '$3cr4t'),
            array('base_url' => 'http://erb.api/')
        );

        $this->userMock = new \stdClass();
        $this->userMock->name = 'user test';
    }

    public function testOnRequestBeforeSend()
    {
        $tokenMock = $this->getTokenMock();
        $expires = time() + 999999;

        $tokenMock
            ->getRefreshToken()
            ->willReturn('refresh_token_value')
            ->shouldBeCalledTimes(1);

        $tokenMock
            ->getAccessToken()
            ->willReturn('access_token_value')
            ->shouldBeCalledTimes(1);

        /** @var \DateTime|ObjectProphecy $dateTimeMock */
        $dateTimeMock = $this->prophesize('\DateTime');

        $dateTimeMock
            ->getTimestamp()
            ->willReturn($expires)
            ->shouldBeCalledTimes(1);

        $tokenMock
            ->getExpiredAt()
            ->willReturn($dateTimeMock)
            ->shouldBeCalledTimes(1);

        $this
            ->tokenBusinessMock
            ->getTokenByUser($this->userMock)
            ->willReturn($tokenMock)
            ->shouldBeCalledTimes(1);

        $securityContextTokenMock = $this->getSecurityContextTokenMock();

        $this->securityContextMock
            ->getToken()
            ->willReturn($securityContextTokenMock->reveal())
            ->shouldBeCalledTimes(3);

        $securityContextTokenMock
            ->getUser()
            ->willReturn($this->userMock)
            ->shouldBeCalledTimes(2);

        $this->loggerMock->info('oauth2_password authenticate', array(
            'user' => $this->userMock,
        ))->shouldBeCalledTimes(1);

        $requestMock = $this->getRequestMock();
        $requestMock->setHeader('Authorization', 'Bearer access_token_value')->shouldBeCalledTimes(1);

        $eventMock = $this->getEventMock();

        $eventMock
            ->offsetGet('request')
            ->willReturn($requestMock)
            ->shouldBeCalledTimes(1);

        $this->oauth2PasswordAuthentication->onRequestBeforeSend($eventMock->reveal());
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Unable to find user token
     */
    public function testOnRequestBeforeSendNoUserTokenException()
    {
        $securityContextTokenMock = $this->getSecurityContextTokenMock();

        $this->securityContextMock
            ->getToken()
            ->willReturn($securityContextTokenMock->reveal())
            ->shouldBeCalledTimes(3);

        $securityContextTokenMock
            ->getUser()
            ->willReturn($this->userMock)
            ->shouldBeCalledTimes(2);

        $this
            ->tokenBusinessMock
            ->getTokenByUser($this->userMock)
            ->willReturn(null)
            ->shouldBeCalledTimes(1);

        $this->oauth2PasswordAuthentication->onRequestBeforeSend($this->getEventMock()->reveal());
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Unable to retrieve user
     */
    public function testOnRequestBeforeSendNoSecurityUserException()
    {
        $eventMock = $this->getEventMock();
        $this->oauth2PasswordAuthentication->onRequestBeforeSend($eventMock->reveal());
    }

    public function testUpdateUserOAuth2Token()
    {
        $securityContextTokenMock = $this->getSecurityContextTokenMock();

        $this->securityContextMock
            ->getToken()
            ->willReturn($securityContextTokenMock->reveal())
            ->shouldBeCalledTimes(3);

        $securityContextTokenMock
            ->getUser()
            ->willReturn($this->userMock)
            ->shouldBeCalledTimes(2);

        $refreshTokenEventMock = $this->getRefreshTokenEventMock();
        $refreshTokenEventMock->offsetGet('access_token')->willReturn('access_token_value')->shouldBeCalledTimes(1);
        $refreshTokenEventMock->offsetGet('refresh_token')->willReturn('refresh_token_value')->shouldBeCalledTimes(1);
        $refreshTokenEventMock->offsetGet('expires_in')->willReturn(99)->shouldBeCalledTimes(1);
        $refreshTokenEventMock->isPropagationStopped()->shouldBeCalled();

        $tokenMock = $this->getTokenMock();

        $this->tokenBusinessMock
            ->updateUserToken(
                $this->userMock,
                'access_token_value',
                'refresh_token_value',
                99
            )
            ->willReturn($tokenMock)
            ->shouldBeCalledTimes(1);

        $clientProperty = new \ReflectionProperty(get_class($this->oauth2PasswordAuthentication), 'client');
        $clientProperty->setAccessible(true);

        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = $clientProperty->getValue($this->oauth2PasswordAuthentication)->getEventDispatcher();

        $refreshTokenEventMock->setDispatcher($dispatcher)->shouldBeCalled();
        $refreshTokenEventMock->setName(RefreshToken::GET_TOKEN_DATA_EVENT)->shouldBeCalled();

        $dispatcher->dispatch(RefreshToken::GET_TOKEN_DATA_EVENT, $refreshTokenEventMock->reveal());
    }

    public function testGenerateToken()
    {
        $responseMock = $this->prophesize('Guzzle\Http\Message\Response');
        $responseMock
            ->json()
            ->shouldBeCalledTimes(1)
            ->willReturn(array(
                'access_token' => 'access_token_value',
                'refresh_token' => 'refresh_token_value',
                'expires_in' => 99,
            ));

        $requestMock = $this->getRequestMock();
        $requestMock
            ->setAuth(12345, '$3cr4t')
            ->shouldBeCalledTimes(1);

        $requestMock
            ->send()
            ->willReturn($responseMock->reveal())
            ->shouldBeCalledTimes(1);

        /** @var ObjectProphecy|Client $clientMock */
        $clientMock = $this->prophesize('Guzzle\Http\Client');
        $clientMock->post(
                null,
                array(),
                array(
                    'grant_type' => 'password',
                    'username' => 'login',
                    'password' => 'password',
                )
            )
            ->willReturn($requestMock->reveal())
            ->shouldBeCalledTimes(1);

        $reflection = new \ReflectionProperty(get_class($this->oauth2PasswordAuthentication), 'client');
        $reflection->setAccessible(true);
        $reflection->setValue($this->oauth2PasswordAuthentication, $clientMock->reveal());

        $result = $this->oauth2PasswordAuthentication->generateToken('login', 'password');
        $expect = array(
            'access_token' => 'access_token_value',
            'expires_in' => 99,
            'refresh_token' => 'refresh_token_value',
        );

        $this->assertEquals($expect, $result);
    }

    public function testGetName()
    {
        $this->assertEquals('oauth2_password', $this->oauth2PasswordAuthentication->getName());
    }

    /**
     * @return ObjectProphecy|Logger
     */
    private function getLoggerMock()
    {
        return $this->prophesize('Symfony\Bridge\Monolog\Logger');
    }

    /**
     * @return ObjectProphecy|TokenBusinessInterface
     */
    private function getTokenBusinessInterfaceMock()
    {
        return $this->prophesize('LaFourchette\HydraApiBundle\Business\TokenBusinessInterface');
    }

    /**
     * @return ObjectProphecy|SecurityContext
     */
    private function getSecurityContextMock()
    {
        return $this->prophesize('Symfony\Component\Security\Core\SecurityContext');
    }

    /**
     * @return ObjectProphecy|Request
     */
    private function getRequestMock()
    {
        return $this->prophesize('Guzzle\Http\Message\Request');
    }

    /**
     * @return ObjectProphecy|UsernamePasswordToken
     */
    private function getSecurityContextTokenMock()
    {
        return $this->prophesize('Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken');
    }

    /**
     * @return ObjectProphecy|TokenInterface
     */
    private function getTokenMock()
    {
        return $this->prophesize('LaFourchette\HydraApiBundle\Entity\TokenInterface');
    }

    /**
     * @return ObjectProphecy|RefreshTokenEvent
     */
    private function getRefreshTokenEventMock()
    {
        return $this->prophesize('LaFourchette\HydraApiBundle\Event\RefreshTokenEvent');
    }

    /**
     * @return Event|ObjectProphecy
     */
    private function getEventMock()
    {
        return $this->prophesize('Guzzle\Common\Event');
    }
}
