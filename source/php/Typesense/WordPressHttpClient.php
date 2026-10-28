<?php

namespace TypesenseSearch\Typesense;

use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * PSR-18 client backed by the WordPress HTTP API.
 *
 * Gives the Typesense client a transport without bundling Guzzle or another
 * HTTP library, which avoids class conflicts with other plugins and honours
 * WordPress proxy and SSL settings.
 */
class WordPressHttpClient implements ClientInterface
{
    public function __construct(private readonly int $timeout = 5)
    {
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $headers = [];
        foreach ($request->getHeaders() as $name => $values) {
            $headers[$name] = implode(', ', $values);
        }

        $response = wp_remote_request((string) $request->getUri(), [
            'method'      => $request->getMethod(),
            'headers'     => $headers,
            'body'        => (string) $request->getBody(),
            'timeout'     => $this->timeout,
            'redirection' => 0,
            'sslverify'   => true,
        ]);

        if (is_wp_error($response)) {
            throw new class ($response->get_error_message(), $request) extends \RuntimeException implements NetworkExceptionInterface {
                public function __construct(string $message, private readonly RequestInterface $request)
                {
                    parent::__construct($message);
                }

                public function getRequest(): RequestInterface
                {
                    return $this->request;
                }
            };
        }

        $responseHeaders = [];
        foreach (wp_remote_retrieve_headers($response) as $name => $value) {
            $responseHeaders[$name] = $value;
        }

        return new Response(
            (int) wp_remote_retrieve_response_code($response),
            $responseHeaders,
            (string) wp_remote_retrieve_body($response)
        );
    }
}
