<?php

namespace App\Tests\Functional\Controller;

use App\Entity\Order;
use App\Entity\OrderLine;
use App\Entity\Product;
use App\Entity\User;
use App\Tests\Functional\WebTestCase;
use Doctrine\ORM\EntityManagerInterface;

class OrderControllerTest extends WebTestCase
{
    protected ?EntityManagerInterface $entityManager = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->entityManager = $this->getEntityManager();
    }

    /**
     * Test: GET /api/store/orders - List orders (should require authentication)
     */
    public function testListOrdersRequiresAuth(): void
    {
        $this->get('/api/store/orders');
        
        // Should be protected
        $this->assertTrue(
            in_array($this->client->getResponse()->getStatusCode(), [401, 403]),
            'Orders endpoint should require authentication'
        );
    }

    /**
     * Test: Create an order with valid data
     */
    public function testCreateOrder(): void
    {
        // Create a test user
        $user = new User();
        $user->setName('Order Test User');
        $user->setEmail('ordertest@example.com');
        $user->setAddress('123 Main Street');
        $user->setPasswordHash(password_hash('Password123', PASSWORD_ARGON2ID));
        $user->setAuthToken($this->generateAuthToken());
        $user->setRole(User::ROLE_USER);

        // Create test products
        $product1 = new Product();
        $product1->setName('Product 1');
        $product1->setSku('PROD-001');
        $product1->setSlug('product-1');
        $product1->setQuantity(100);
        $product1->setDescription('Test product 1');
        $product1->setPrice(1999);
        $product1->setIsActive(true);

        $product2 = new Product();
        $product2->setName('Product 2');
        $product2->setSku('PROD-002');
        $product2->setSlug('product-2');
        $product2->setQuantity(50);
        $product2->setDescription('Test product 2');
        $product2->setPrice(2999);
        $product2->setIsActive(true);

        // Persist entities
        $this->entityManager->persist($user);
        $this->entityManager->persist($product1);
        $this->entityManager->persist($product2);
        $this->entityManager->flush();

        // Create an order with order lines
        $order = new Order();
        $order->setUser($user);
        $order->setDateOfCreation(new \DateTime());
        $order->setNumberOrder(1001);

        // Add order lines
        $line1 = new OrderLine();
        $line1->setOrder($order);
        $line1->setProduct($product1);
        $line1->setQuantity(2);

        $line2 = new OrderLine();
        $line2->setOrder($order);
        $line2->setProduct($product2);
        $line2->setQuantity(1);

        $this->entityManager->persist($order);
        $this->entityManager->persist($line1);
        $this->entityManager->persist($line2);
        $this->entityManager->flush();

        // Verify the order was created
        $this->assertNotNull($order->getId());
        $this->assertNotNull($line1->getId());
        $this->assertNotNull($line2->getId());
        $this->assertSame($order, $line1->getOrder());
        $this->assertSame($order, $line2->getOrder());
    }

    /**
     * Test: Get order details
     */
    public function testGetOrderDetails(): void
    {
        // Create user and products
        $user = new User();
        $user->setName('Detail Test User');
        $user->setEmail('detailtest@example.com');
        $user->setAddress('456 Oak Street');
        $user->setPasswordHash(password_hash('Password123', PASSWORD_ARGON2ID));
        $user->setAuthToken($this->generateAuthToken());
        $user->setRole(User::ROLE_USER);

        $product = new Product();
        $product->setName('Detail Product');
        $product->setSku('DETAIL-001');
        $product->setSlug('detail-product');
        $product->setQuantity(10);
        $product->setDescription('Product for order detail test');
        $product->setPrice(1499);
        $product->setIsActive(true);

        // Create order
        $order = new Order();
        $order->setUser($user);
        $order->setDateOfCreation(new \DateTime());
        $order->setNumberOrder(1002);

        $line = new OrderLine();
        $line->setOrder($order);
        $line->setProduct($product);
        $line->setQuantity(1);

        $this->entityManager->persist($user);
        $this->entityManager->persist($product);
        $this->entityManager->persist($order);
        $this->entityManager->persist($line);
        $this->entityManager->flush();

        // Verify order structure
        $this->assertNotNull($line->getId());
        $this->assertEquals('DETAIL-001', $line->getProduct()->getSku());
        $this->assertEquals(1, $line->getQuantity());
    }

    /**
     * Generate a random auth token for tests
     */
    private function generateAuthToken(): string
    {
        return bin2hex(random_bytes(32));
    }
}
