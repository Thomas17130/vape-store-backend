<?php

namespace App\Tests\Functional\Controller;

use App\Entity\Brand;
use App\Tests\Functional\WebTestCase;
use Doctrine\ORM\EntityManagerInterface;

class BrandControllerTest extends WebTestCase
{
    private EntityManagerInterface $entityManager;

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
        $brand1->setSlug('brand-one');

        $brand2 = new Brand();
        $brand2->setName('Brand Two');
        $brand2->setSlug('brand-two');

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

    /**
     * Test: GET /api/brands/{id} - Get specific brand
     */
    public function testShowBrand(): void
    {
        // Create test brand
        $brand = new Brand();
        $brand->setName('Test Brand');
        $brand->setSlug('test-brand');

        $this->entityManager->persist($brand);
        $this->entityManager->flush();

        // Get the brand
        $this->get('/api/brands/'.$brand->getId());
        
        $this->assertResponseOk();
        $response = $this->getJsonResponse();
        $this->assertJsonHasKeys(['id', 'name', 'slug']);
        $this->assertEquals('Test Brand', $response['name']);
    }

    /**
     * Test: GET /api/brands/not-found - Brand not found
     */
    public function testShowBrandNotFound(): void
    {
        $this->get('/api/brands/999999');
        
        $this->assertResponseNotFound();
    }
}
