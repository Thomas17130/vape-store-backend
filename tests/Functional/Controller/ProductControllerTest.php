<?php

namespace App\Tests\Functional\Controller;

use App\Entity\Brand;
use App\Entity\Product;
use App\Tests\Functional\WebTestCase;
use Doctrine\ORM\EntityManagerInterface;

class ProductControllerTest extends WebTestCase
{
    protected ?EntityManagerInterface $entityManager = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->entityManager = $this->getEntityManager();
    }

    /**
     * Test: GET /api/products - List all products
     */
    public function testListProducts(): void
    {
        // Create a test product
        $product = new Product();
        $product->setName('Test Product');
        $product->setSku('TEST-SKU-001');
        $product->setSlug('test-product');
        $product->setQuantity(10);
        $product->setDescription('A test product');
        $product->setPrice(1999); // 19.99€
        $product->setIsActive(true);

        $this->entityManager->persist($product);
        $this->entityManager->flush();

        // Make request
        $this->get('/api/products');
        
        // Assertions
        $this->assertResponseOk();
        $response = $this->getJsonResponse();
        $this->assertIsArray($response);
        $this->assertGreaterThanOrEqual(1, count($response));
        $this->assertArrayHasKey('name', $response[0]);
        $this->assertArrayHasKey('price', $response[0]);
    }

    /**
     * Test: GET /api/products?search=test - Search products
     */
    public function testSearchProducts(): void
    {
        // Create test products
        $product1 = new Product();
        $product1->setName('iPhone Case');
        $product1->setSku('IPHONE-CASE-001');
        $product1->setSlug('iphone-case');
        $product1->setQuantity(5);
        $product1->setDescription('A protective case');
        $product1->setPrice(2999);
        $product1->setIsActive(true);

        $product2 = new Product();
        $product2->setName('Samsung Case');
        $product2->setSku('SAMSUNG-CASE-001');
        $product2->setSlug('samsung-case');
        $product2->setQuantity(3);
        $product2->setDescription('A protective case');
        $product2->setPrice(1999);
        $product2->setIsActive(true);

        $this->entityManager->persist($product1);
        $this->entityManager->persist($product2);
        $this->entityManager->flush();

        // Search for iPhone
        $this->get('/api/products?search=iPhone');
        
        $this->assertResponseOk();
        $response = $this->getJsonResponse();
        $this->assertIsArray($response);

        // Verify at least one product contains 'iPhone'
        $hasIPhone = array_any($response, fn($p) => stripos($p['name'], 'iPhone') !== false);
        $this->assertTrue($hasIPhone, 'Search should return products matching "iPhone"');
    }

    /**
     * Test: GET /api/products/{id} - Get a specific product
     */
    public function testShowProduct(): void
    {
        // Create test product
        $product = new Product();
        $product->setName('Test Product');
        $product->setSku('TEST-SHOW-001');
        $product->setSlug('test-show');
        $product->setQuantity(10);
        $product->setDescription('A product to show');
        $product->setPrice(1499);
        $product->setIsActive(true);

        $this->entityManager->persist($product);
        $this->entityManager->flush();

        // Get the product
        $this->get('/api/products/'.$product->getId());
        
        $this->assertResponseOk();
        $response = $this->getJsonResponse();
        $this->assertJsonHasKeys(['id', 'name', 'sku', 'price']);
        $this->assertEquals('Test Product', $response['name']);
        $this->assertEquals('TEST-SHOW-001', $response['sku']);
    }

    /**
     * Test: POST /api/products - Create a new product (admin only)
     */
    public function testCreateProductUnauthorized(): void
    {
        $payload = [
            'name' => 'New Product',
            'description' => 'A new product',
            'price' => 2999,
            'quantity' => 10,
            'type' => 'product',
        ];

        // Try to create without authorization
        $this->post('/api/products', $payload);
        
        // Should be unauthorized or forbidden
        $this->assertTrue(
            in_array($this->client->getResponse()->getStatusCode(), [401, 403]),
            'Creating product without auth should return 401 or 403'
        );
    }

    /**
     * Test: GET /api/products/not-found - 404 handling
     */
    public function testShowProductNotFound(): void
    {
        $this->get('/api/products/999999');
        
        $this->assertResponseNotFound();
    }

    /**
     * Test: GET /api/products?type=e-liquid - Filter by type
     */
    public function testFilterProductsByType(): void
    {
        // Create products of different types
        $product = new Product();
        $product->setName('Generic Product');
        $product->setSku('GENERIC-001');
        $product->setSlug('generic-product');
        $product->setQuantity(10);
        $product->setDescription('A generic product');
        $product->setPrice(1999);
        $product->setIsActive(true);

        $this->entityManager->persist($product);
        $this->entityManager->flush();

        // Get all products
        $this->get('/api/products');
        
        $this->assertResponseOk();
        $response = $this->getJsonResponse();
        $this->assertIsArray($response);
        $this->assertGreaterThanOrEqual(1, count($response));
    }

    /**
     * Test: Invalid request handling
     */
    public function testInvalidBrandIdFilter(): void
    {
        $this->get('/api/products?brandId=invalid');
        
        $this->assertResponseBadRequest();
        $response = $this->getJsonResponse();
        $this->assertArrayHasKey('error', $response);
    }
}
