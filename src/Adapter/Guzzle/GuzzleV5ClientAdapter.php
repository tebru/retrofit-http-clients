<?php
/*
 * Copyright (c) 2015 Nate Brunette.
 * Distributed under the MIT License (http://opensource.org/licenses/MIT)
 */

namespace Tebru\Retrofit\HttpClient\Adapter\Guzzle;

use Exception;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Message\Request;
use GuzzleHttp\Message\ResponseInterface;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response as Psr7Response;
use GuzzleHttp\Ring\Future\FutureInterface;
use GuzzleHttp\Stream\Stream;
use Psr\Http\Message\RequestInterface;
use Tebru;
use Tebru\Retrofit\Adapter\HttpClientAdapter;
use Tebru\Retrofit\Exception\RequestException;
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
     * @param RequestInterface $request
     * @return Psr7Response
     */
    public function send(RequestInterface $request)
    {
        $request = $this->createRequest($request);
        $response = $this->client->send($request);

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
     * @param RequestInterface $request
     * @param \Tebru\Retrofit\Http\Callback $callback
     * @return null
     */
    public function sendAsync(RequestInterface $request, Callback $callback)
    {
        $request = $this->createRequest($request, true);

        /** @var FutureInterface $response */
        $response = $this->client->send($request);

        $this->promises[] = $response
            ->then(
                function (ResponseInterface $response) {
                    return new Psr7Response(
                        $response->getStatusCode(),
                        $response->getHeaders(),
                        $response->getBody(),
                        $response->getProtocolVersion(),
                        $response->getReasonPhrase()
                    );
                },
                function (Exception $exception) use ($callback) {
                    /** @var \GuzzleHttp\Exception\RequestException $exception */
                    $requestException = new RequestException(
                        $exception->getMessage(),
                        $exception->getCode(),
                        $exception->getPrevious(),
                        $exception->getRequest(),
                        $exception->getResponse()
                    );

                    $callback->onFailure($requestException);
                }
            )
            ->then(
                function (Psr7Response $response) use ($callback) {
                    $callback->onResponse($response);
                }
            );
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

    /**
     * Create a Guzzle5 request object
     *
     * @param RequestInterface $request
     * @param bool $async
     * @return Request
     */
    private function createRequest(RequestInterface $request, $async = false)
    {
        $body = Stream::factory($request->getBody());
        $uri = rawurldecode((string)$request->getUri());
        $headers = $request->getHeaders();

        // fixes issue with host getting applied twice
        if (isset($headers['host'])) {
            unset($headers['host']);
        }

        $options = ($async) ? ['future' => true] : [];

        return new Request($request->getMethod(), $uri, $headers, $body, $options);
    }
}
