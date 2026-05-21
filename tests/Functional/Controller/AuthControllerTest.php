<?php

namespace App\Tests\Functional\Controller;

use App\Entity\User;
use App\Tests\Functional\WebTestCase;
use Doctrine\ORM\EntityManagerInterface;

class AuthControllerTest extends WebTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->entityManager = $this->getEntityManager();
    }

    /**
     * Test: POST /api/auth/signup - User registration with valid data
     */
    public function testSignupSuccess(): void
    {
        $payload = [
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'address' => '123 Main Street',
            'password' => 'SecurePassword123',
        ];

        $this->post('/api/auth/signup', $payload);
        
        $this->assertResponseCreated();
        $response = $this->getJsonResponse();
        $this->assertJsonHasKeys(['message', 'token', 'user']);
        $this->assertEquals('Inscription reussie', $response['message']);
        $this->assertNotEmpty($response['token']);
        $this->assertEquals('john.doe@example.com', $response['user']['email']);
    }

    /**
     * Test: POST /api/auth/signup - Missing required fields
     */
    public function testSignupMissingFields(): void
    {
        $payload = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            // Missing address and password
        ];

        $this->post('/api/auth/signup', $payload);
        
        $this->assertResponseBadRequest();
        $response = $this->getJsonResponse();
        $this->assertArrayHasKey('error', $response);
    }

    /**
     * Test: POST /api/auth/signup - Invalid email format
     */
    public function testSignupInvalidEmail(): void
    {
        $payload = [
            'name' => 'John Doe',
            'email' => 'not-an-email',
            'address' => '123 Main Street',
            'password' => 'SecurePassword123',
        ];

        $this->post('/api/auth/signup', $payload);
        
        $this->assertResponseBadRequest();
        $response = $this->getJsonResponse();
        $this->assertArrayHasKey('error', $response);
        $this->assertStringContainsString('e-mail', strtolower($response['error']));
    }

    /**
     * Test: POST /api/auth/signup - Password too short
     */
    public function testSignupPasswordTooShort(): void
    {
        $payload = [
            'name' => 'John Doe',
            'email' => 'john.short@example.com',
            'address' => '123 Main Street',
            'password' => 'Short1', // Less than 8 characters
        ];

        $this->post('/api/auth/signup', $payload);
        
        $this->assertResponseBadRequest();
        $response = $this->getJsonResponse();
        $this->assertArrayHasKey('error', $response);
        $this->assertStringContainsString('caracteres', strtolower($response['error']));
    }

    /**
     * Test: POST /api/auth/signup - Duplicate email
     */
    public function testSignupDuplicateEmail(): void
    {
        $email = 'duplicate@example.com';
        
        // First signup
        $payload1 = [
            'name' => 'User One',
            'email' => $email,
            'address' => '123 Main Street',
            'password' => 'SecurePassword123',
        ];
        
        $this->post('/api/auth/signup', $payload1);
        $this->assertResponseCreated();
        $this->flushDatabase();

        // Second signup with same email
        $payload2 = [
            'name' => 'User Two',
            'email' => $email,
            'address' => '456 Oak Street',
            'password' => 'AnotherPassword123',
        ];
        
        $this->post('/api/auth/signup', $payload2);
        
        $this->assertResponseConflict();
        $response = $this->getJsonResponse();
        $this->assertArrayHasKey('error', $response);
        $this->assertStringContainsString('existe', strtolower($response['error']));
    }

    /**
     * Test: POST /api/auth/login - Successful login
     */
    public function testLoginSuccess(): void
    {
        // Create a user first
        $user = new User();
        $user->setName('Login Test User');
        $user->setEmail('logintest@example.com');
        $user->setAddress('123 Main Street');
        $user->setPasswordHash(password_hash('TestPassword123', PASSWORD_ARGON2ID));
        $user->setAuthToken($this->generateAuthToken());
        $user->setRole(User::ROLE_USER);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        // Now try to login
        $payload = [
            'email' => 'logintest@example.com',
            'password' => 'TestPassword123',
        ];

        $this->post('/api/auth/login', $payload);
        
        $this->assertResponseOk();
        $response = $this->getJsonResponse();
        $this->assertJsonHasKeys(['message', 'token', 'user']);
        $this->assertNotEmpty($response['token']);
    }

    /**
     * Test: POST /api/auth/login - Wrong password
     */
    public function testLoginWrongPassword(): void
    {
        // Create a user
        $user = new User();
        $user->setName('Wrong Password Test');
        $user->setEmail('wrongpass@example.com');
        $user->setAddress('123 Main Street');
        $user->setPasswordHash(password_hash('CorrectPassword123', PASSWORD_ARGON2ID));
        $user->setAuthToken($this->generateAuthToken());
        $user->setRole(User::ROLE_USER);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        // Try to login with wrong password
        $payload = [
            'email' => 'wrongpass@example.com',
            'password' => 'WrongPassword123',
        ];

        $this->post('/api/auth/login', $payload);
        
        // Should fail
        $this->assertTrue(
            in_array($this->client->getResponse()->getStatusCode(), [400, 401, 404]),
            'Login with wrong password should fail'
        );
    }

    /**
     * Test: POST /api/auth/login - User not found
     */
    public function testLoginUserNotFound(): void
    {
        $payload = [
            'email' => 'nonexistent@example.com',
            'password' => 'SomePassword123',
        ];

        $this->post('/api/auth/login', $payload);
        
        // Should fail
        $this->assertTrue(
            in_array($this->client->getResponse()->getStatusCode(), [400, 401, 404]),
            'Login with non-existent user should fail'
        );
    }

    /**
     * Test: POST /api/auth/login - Missing fields
     */
    public function testLoginMissingFields(): void
    {
        $payload = [
            'email' => 'test@example.com',
            // Missing password
        ];

        $this->post('/api/auth/login', $payload);
        
        $this->assertResponseBadRequest();
        $response = $this->getJsonResponse();
        $this->assertArrayHasKey('error', $response);
    }

    /**
     * Generate a random auth token for tests
     */
    private function generateAuthToken(): string
    {
        return bin2hex(random_bytes(32));
    }
}
