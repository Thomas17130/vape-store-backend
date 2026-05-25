<?php

namespace App\Tests\Functional\Controller;

use App\Entity\Brand;
use App\Tests\Functional\WebTestCase;
use Doctrine\ORM\EntityManagerInterface;

class BrandControllerTest extends WebTestCase
{
    protected ?EntityManagerInterface $entityManager = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->entityManager = $this->getEntityManager();
    }

    /**
     * Test: GET /api/brands - List all brands
     */
    public function testListBrands(): void
    {
        // Create test brands
        $brand1 = new Brand();
        $brand1->setName('Brand One');

        $brand2 = new Brand();
        $brand2->setName('Brand Two');

        $this->entityManager->persist($brand1);
        $this->entityManager->persist($brand2);
        $this->entityManager->flush();

        // Get brands
        $this->get('/api/brands');
        
        $this->assertResponseOk();
        $response = $this->getJsonResponse();
        $this->assertIsArray($response);
        $this->assertGreaterThanOrEqual(2, count($response));
    }

    public function testCreateBrandRequiresAdmin(): void
    {
        $this->post('/api/brands', ['name' => 'Protected Brand']);

        $this->assertResponseForbidden();
    }
}
