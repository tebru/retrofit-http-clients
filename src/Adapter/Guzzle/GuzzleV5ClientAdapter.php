<?php
/*
 * Copyright (c) 2015 Nate Brunette.
 * Distributed under the MIT License (http://opensource.org/licenses/MIT)
 */

namespace Tebru\Retrofit\HttpClient\Adapter\Guzzle;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Message\Request;
use GuzzleHttp\Message\ResponseInterface;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response as Psr7Response;
use GuzzleHttp\Psr7\Request as Psr7Request;
use GuzzleHttp\Ring\Future\FutureInterface;
use GuzzleHttp\Stream\Stream;
use Tebru;
use Tebru\Retrofit\Adapter\HttpClientAdapter;
use Tebru\Retrofit\Http\Callback;

/**
 * Class GuzzleV5ClientAdapter
 *
 * Wrapper around version 5 of guzzlehttp/guzzle
 *
 * @author Nate Brunette <n@tebru.net>
 */
class GuzzleV5ClientAdapter implements HttpClientAdapter
{
    /**
     * @var ClientInterface
     */
    private $client;

    /**
     * @var PromiseInterface[]
     */
    private $promises = [];

    /**
     * Constructor
     *
     * @param ClientInterface $client
     */
    public function __construct(ClientInterface $client)
    {
        $version = (int)ClientInterface::VERSION;
        Tebru\assertSame(5, $version, 'Guzzle client must be at version 5, version %d found', $version);

        $this->client = $client;
    }

    /**
     * Make a request
     *
     * @param string $method
     * @param string $uri
     * @param array $headers
     * @param string $body
     * @return Psr7Response
     */
    public function send($method, $uri, array $headers = [], $body = null)
    {
        if (null !== $body) {
            $body = Stream::factory($body);
        }

        $response = $this->client->send(new Request($method, $uri, $headers, $body));

        return new Psr7Response(
            $response->getStatusCode(),
            $response->getHeaders(),
            $response->getBody(),
            $response->getProtocolVersion(),
            $response->getReasonPhrase()
        );
    }

    /**
     * Send asynchronous guzzle request
     *
     * @param Psr7Request $request
     * @param \Tebru\Retrofit\Http\Callback $callback
     * @return null
     */
    public function sendAsync(Psr7Request $request, Callback $callback)
    {
        $request = new Request(
            $request->getMethod(),
            (string) $request->getUri(),
            $request->getHeaders(),
            $request->getBody(),
            ['future' => true]
        );

        /** @var FutureInterface $response */
        $response = $this->client->send($request);

        $this->promises[] = $response
            ->then(function (ResponseInterface $response) {
                return new Psr7Response(
                    $response->getStatusCode(),
                    $response->getHeaders(),
                    $response->getBody(),
                    $response->getProtocolVersion(),
                    $response->getReasonPhrase()
                );
            })
            ->then($callback->success(), $callback->failure())
        ;
    }

    /**
     * Resolve all promises
     *
     * @return null
     */
    public function wait()
    {
        foreach ($this->promises as $promise) {
            $promise->wait();
        }
    }
}
