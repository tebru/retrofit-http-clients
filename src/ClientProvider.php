<?php
/*
 * Copyright (c) 2015 Nate Brunette.
 * Distributed under the MIT License (http://opensource.org/licenses/MIT)
 */

namespace Tebru\Retrofit\HttpClient;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientInterface as GuzzleClientInterface;
use Tebru\Retrofit\Adapter\HttpClientAdapter;
use Tebru\Retrofit\Exception\RetrofitException;
use Tebru\Retrofit\HttpClient\Adapter\Guzzle\GuzzleV5ClientAdapter;
use Tebru\Retrofit\HttpClient\Adapter\Guzzle\GuzzleV6ClientAdapter;

/**
 * Class ClientProvider
 *
 * @author Nate Brunette <n@tebru.net>
 */
class ClientProvider
{
    /**
     * The http client, currently only supports Guzzle
     *
     * @var mixed
     */
    private $httpClient;

    /**
     * Set the http client
     *
     * @param mixed $client
     * @return $this
     * @throws RetrofitException
     */
    public function setClient($client)
    {
        if (!$client instanceof GuzzleClientInterface)
        {
            throw new RetrofitException(sprintf('Currently, the only supported http clients are \GuzzleHttp\ClientInterface, found (%s)', get_class($client)));
        }

        $this->httpClient = $client;

        return $this;
    }

    /**
     * Get the client
     *
     * @return HttpClientAdapter
     */
    public function getClient()
    {
        if (null == $this->httpClient) {
            return $this->getClientAdapter();
        }

        return $this->getClientAdapter($this->httpClient);
    }

    /**
     * Get the client adapter
     *
     * If a client is not specified, create a new one
     *
     * @param mixed|null $client
     * @return HttpClientAdapter
     */
    private function getClientAdapter($client = null)
    {
        if (!interface_exists('GuzzleHttp\ClientInterface')) {
            return new RetrofitException('It appears you do not have an http client installed.  Please install guzzlehttp/guzzle.');
        }

        $version = (int)GuzzleClientInterface::VERSION;

        if (null === $client) {
            $client = new GuzzleClient();
        }

        if (5 === $version) {
            return new GuzzleV5ClientAdapter($client);
        }

        return new GuzzleV6ClientAdapter($client);
    }
}
