<?php

declare(strict_types=1);

namespace Magephi\Component;

use JsonException;
use Magephi\Exception\DockerHubException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class DockerHub
{
    private const API_ENDPOINT = 'https://hub.docker.com/v2/repositories/ajardin/%s/tags';

    private HttpClientInterface $httpClient;

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    /**
     * Retrieves the tags associated to the given image.
     *
     * @param string $image
     *
     * @throws DockerHubException
     * @throws TransportExceptionInterface
     *
     * @return string[]
     */
    public function getImageTags(string $image): array
    {
        $response = $this->httpClient->request(Request::METHOD_GET, sprintf(self::API_ENDPOINT, $image));
        $parsedResponse = $this->parseResponse($response);

        if (\is_array($parsedResponse) && \array_key_exists('results', $parsedResponse)
            && \is_array($parsedResponse['results'])) {
            $tags = array_column($parsedResponse['results'], 'name');
            rsort($tags);

            return $tags;
        }

        throw new DockerHubException('Unable to retrieve the image tags.');
    }

    /**
     * Analyzes the Docker Hub API response by checking the status code and by decoding the JSON content.
     *
     * @param ResponseInterface $response
     *
     * @throws DockerHubException
     *
     * @return array<array|int|string>
     */
    private function parseResponse(ResponseInterface $response): array
    {
        try {
            $rawContent = $response->getContent(true);
            $parsedContent = (!empty($rawContent) && $rawContent !== 'null')
                ? json_decode($rawContent, true, 512, JSON_THROW_ON_ERROR) : [];
        } catch (ExceptionInterface | JsonException $exception) {
            throw new DockerHubException(
                sprintf("Unable to parse the Docker Hub API response.\n%s", $exception->getMessage())
            );
        }

        return $parsedContent;
    }
}
