<?php

namespace App\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase as BaseWebTestCase;
use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Base class for functional tests
 *
 * Provides helper methods for making HTTP requests and assertions
 */
abstract class WebTestCase extends BaseWebTestCase
{
    protected ?Client $client = null;
    protected ?EntityManagerInterface $entityManager = null;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->client = static::createClient();
        
        // Get the service container
        $this->entityManager = $this->getContainer()->get(EntityManagerInterface::class);
        
        // Begin a transaction for easier cleanup
        $this->entityManager->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->entityManager && $this->entityManager->getConnection()->isTransactionActive()) {
            $this->entityManager->rollback();
        }

        parent::tearDown();
    }

    /**
     * Make a GET request
     */
    protected function get(string $uri, array $headers = []): Crawler
    {
        return $this->client->request('GET', $uri, [], [], $this->buildHeaders($headers));
    }

    /**
     * Make a POST request with JSON payload
     */
    protected function post(string $uri, array $payload = [], array $headers = []): Crawler
    {
        return $this->client->request('POST', $uri, [], [], $this->buildHeaders($headers), json_encode($payload));
    }

    /**
     * Make a PUT request with JSON payload
     */
    protected function put(string $uri, array $payload = [], array $headers = []): Crawler
    {
        return $this->client->request('PUT', $uri, [], [], $this->buildHeaders($headers), json_encode($payload));
    }

    /**
     * Make a PATCH request with JSON payload
     */
    protected function patch(string $uri, array $payload = [], array $headers = []): Crawler
    {
        return $this->client->request('PATCH', $uri, [], [], $this->buildHeaders($headers), json_encode($payload));
    }

    /**
     * Make a DELETE request
     */
    protected function delete(string $uri, array $headers = []): Crawler
    {
        return $this->client->request('DELETE', $uri, [], [], $this->buildHeaders($headers));
    }

    /**
     * Build headers for HTTP requests
     */
    protected function buildHeaders(array $headers = []): array
    {
        $defaults = [
            'CONTENT_TYPE' => 'application/json',
            'ACCEPT' => 'application/json',
        ];

        return array_merge($defaults, $headers);
    }

    /**
     * Get the JSON response content
     */
    protected function getJsonResponse(): array
    {
        $content = $this->client->getResponse()->getContent();
        return json_decode($content, true) ?? [];
    }

    /**
     * Assert that the response status code is 200 OK
     */
    protected function assertResponseOk(): void
    {
        $this->assertResponseStatusCodeSame(200);
    }

    /**
     * Assert that the response status code is 201 Created
     */
    protected function assertResponseCreated(): void
    {
        $this->assertResponseStatusCodeSame(201);
    }

    /**
     * Assert that the response status code is 204 No Content
     */
    protected function assertResponseNoContent(): void
    {
        $this->assertResponseStatusCodeSame(204);
    }

    /**
     * Assert that the response status code is 400 Bad Request
     */
    protected function assertResponseBadRequest(): void
    {
        $this->assertResponseStatusCodeSame(400);
    }

    /**
     * Assert that the response status code is 401 Unauthorized
     */
    protected function assertResponseUnauthorized(): void
    {
        $this->assertResponseStatusCodeSame(401);
    }

    /**
     * Assert that the response status code is 403 Forbidden
     */
    protected function assertResponseForbidden(): void
    {
        $this->assertResponseStatusCodeSame(403);
    }

    /**
     * Assert that the response status code is 404 Not Found
     */
    protected function assertResponseNotFound(): void
    {
        $this->assertResponseStatusCodeSame(404);
    }

    /**
     * Assert that the response status code is 409 Conflict
     */
    protected function assertResponseConflict(): void
    {
        $this->assertResponseStatusCodeSame(409);
    }

    /**
     * Assert that the JSON response contains a key
     */
    protected function assertJsonHasKey(string $key): void
    {
        $json = $this->getJsonResponse();
        $this->assertArrayHasKey($key, $json, "JSON response does not contain key '$key'");
    }

    /**
     * Assert that the JSON response contains multiple keys
     */
    protected function assertJsonHasKeys(array $keys): void
    {
        foreach ($keys as $key) {
            $this->assertJsonHasKey($key);
        }
    }

    /**
     * Get the EntityManager for test database operations
     */
    protected function getEntityManager(): EntityManagerInterface
    {
        return $this->entityManager;
    }

    /**
     * Flush changes to the database
     */
    protected function flushDatabase(): void
    {
        $this->entityManager->flush();
    }
}
