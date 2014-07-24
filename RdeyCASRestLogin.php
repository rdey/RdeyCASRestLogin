<?php

require 'vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\SessionCookieJar;
use GuzzleHttp\Exception\RequestException;

/**
 * Class RdeyCASRestLogin
 */
class RdeyCASRestLogin
{
    protected $client;
    protected $cookieJar;
    protected $ticketGrantingTicket;
    protected $loginTicket;
    protected $serviceTicket;

    protected $username;
    protected $password;
    protected $serviceName;
    protected $baseUrl;

    /**
     * @param string $baseUrl     Base URL to CAS server
     * @param string $username    Username to validate against CAS
     * @param string $password    Password to validate against CAS
     * @param string $serviceName Name of service to let CAS authorize against
     */
    public function __construct($baseUrl, $username, $password, $serviceName)
    {
        $this->cookieJar = new SessionCookieJar(uniqid('cas_sess_'));
        $this->client = new Client(['base_url' => $baseUrl]);

        $this->username = $username;
        $this->password = $password;
        $this->serviceName = $serviceName;
        $this->baseUrl = $baseUrl;
    }

    /**
     * Performs the login and check if user has required roles
     *
     * @param array $roles Array of strings representing required roles
     *
     * @return bool
     */
    public function login(array $roles = [])
    {
        try {
            $userDetails = $this->setLoginTicket()
                ->setTicketGrantingTicket()
                ->setServiceTicket()
                ->getUserDetails();

        } catch (RequestException $e) {
            $statusCode = $e->getResponse()->getStatusCode();
            $reasonText = $e->getResponse()->getReasonPhrase();
            http_response_code($statusCode);
            die("$statusCode $reasonText");
        }

        if (count(array_intersect($roles, $userDetails['roles'])) == count($roles)) {
            return true;
        }

        return false;
    }

    /**
     * Get the Ticket Granting Ticket (TGT) needed for service authorization
     *
     * @return $this
     */
    private function setTicketGrantingTicket()
    {
        $request = $this->client->createRequest('POST', 'login', ['cookies' => $this->cookieJar]);
        $request->setQuery(
            [
                'gateway' => 'true'
            ]
        );

        /** @var $postBody \GuzzleHttp\Post\PostBody */
        $postBody = $request->getBody();
        $postBody->setField('username', $this->username);
        $postBody->setField('password', $this->password);
        $postBody->setField('lt', $this->loginTicket);

        $this->client->send($request);

        foreach ($this->cookieJar->toArray() as $c) {
            if (!empty($c['Name']) && $c['Name'] == 'tgt' && !empty($c['Value'])) {
                $this->ticketGrantingTicket = $c['Value'];
                break;
            }
        }

        return $this;
    }

    /**
     * Get the Login Ticket (LT) needed to initiate login
     *
     * @return $this
     */
    private function setLoginTicket()
    {
        $body = $this->client->post('loginTicket')->getBody();
        preg_match('/LT-?(.*)/', $body, $ticket);
        $this->loginTicket = $ticket[0];

        return $this;
    }

    /**
     * Get the Service Ticket (ST) to verify the authorization to the service
     *
     * @return $this
     */
    private function setServiceTicket()
    {
        $request = $this->client->createRequest(
            'GET',
            'login',
            ['cookies' => $this->cookieJar, 'allow_redirects' => false]
        );
        $request->setQuery(
            [
                'service' => $this->serviceName
            ]
        );

        $response = $this->client->send($request);
        $url = parse_url($response->getHeader('location'));
        $this->serviceTicket = explode('=', $url['query'])[1];

        return $this;
    }

    /**
     * Get user details associated with logged in user from CAS server
     *
     * @return array Represents the user details
     */
    private function getUserDetails()
    {
        $request = $this->client->createRequest('GET', 'serviceValidate');
        $request->setQuery(
            [
                'service' => $this->serviceName,
                'ticket' => $this->serviceTicket
            ]
        );

        $response = $this->client->send($request);
        $xml = $response->xml(['ns' => 'cas', 'ns_is_prefix' => true]);
        $attributes = (array)$xml->authenticationSuccess->attributes;

        return array_map(
            function ($value) {
                return json_decode($value);
            },
            $attributes
        );
    }
}
