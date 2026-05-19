<?php

namespace App\Controller;

use App\Entity\Category;
use App\Entity\Product;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use App\Support\ProductDataMapper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/store')]
class StorefrontController extends AbstractController
{
    #[Route('/home', name: 'api_store_home', methods: ['GET'])]
    public function home(ProductRepository $productRepository, CategoryRepository $categoryRepository): JsonResponse
    {
        $latestProducts = $productRepository->createQueryBuilder('p')
            ->andWhere('p.isActive = :active')->setParameter('active', true)
            ->orderBy('p.updatedAt', 'DESC')
            ->setMaxResults(32)
            ->getQuery()
            ->getResult();

        $popularProducts = $productRepository->createQueryBuilder('p')
            ->andWhere('p.isActive = :active')->setParameter('active', true)
            ->orderBy('p.seenCount', 'DESC')
            ->addOrderBy('p.createdAt', 'DESC')
            ->setMaxResults(32)
            ->getQuery()
            ->getResult();

        $categories = $categoryRepository->findBy([], ['name' => 'ASC']);

        return new JsonResponse([
            'hero' => array_map(static fn (Product $product) => ProductDataMapper::toArray($product), array_slice($latestProducts, 0, 5)),
            'trending' => array_map(static fn (Product $product) => ProductDataMapper::toArray($product), array_slice($popularProducts, 0, 10)),
            'deals' => array_map(
                static fn (Product $product) => ProductDataMapper::toArray($product),
                array_values(array_filter($latestProducts, static fn (Product $p) => $p->getSalePrice() !== null))
            ),
            'latest' => array_map(static fn (Product $product) => ProductDataMapper::toArray($product), array_slice($latestProducts, 0, 4)),
            'popular' => array_map(static fn (Product $product) => ProductDataMapper::toArray($product), $popularProducts),
            'categories' => array_map(static fn (Category $category) => [
                'id' => $category->getId(),
                'name' => $category->getName(),
                'slug' => $category->getSlug(),
                'parentId' => $category->getParent()?->getId(),
            ], $categories),
        ]);
    }

    #[Route('/products', name: 'api_store_products', methods: ['GET'])]
    public function products(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(60, max(1, (int) $request->query->get('limit', 20)));
        $query = trim((string) $request->query->get('q', ''));
        $type = trim((string) $request->query->get('type', ''));
        $categoryId = $request->query->get('categoryId');
        $minPrice = $request->query->get('minPrice');
        $maxPrice = $request->query->get('maxPrice');
        $sort = trim((string) $request->query->get('sort', 'featured'));

        $qb = $entityManager->getRepository(Product::class)->createQueryBuilder('p')
            ->leftJoin('p.brand', 'b')->addSelect('b')
            ->leftJoin('p.categories', 'c')
            ->distinct()
            ->andWhere('p.isActive = :active')->setParameter('active', true);

        if ($query !== '') {
            $qb->andWhere('LOWER(p.name) LIKE :q OR LOWER(p.description) LIKE :q OR LOWER(b.name) LIKE :q OR LOWER(p.sku) LIKE :q')
                ->setParameter('q', '%'.mb_strtolower($query).'%');
        }

        if ($type === 'box') {
            $qb->andWhere('p INSTANCE OF App\\Entity\\Box');
        } elseif ($type === 'e-liquid') {
            $qb->andWhere('p INSTANCE OF App\\Entity\\Eliquid');
        } elseif ($type === 'product') {
            $qb->andWhere('p NOT INSTANCE OF App\\Entity\\Box')->andWhere('p NOT INSTANCE OF App\\Entity\\Eliquid');
        }

        if ($categoryId !== null && $categoryId !== '' && is_numeric((string) $categoryId)) {
            $qb->andWhere('c.id = :categoryId')->setParameter('categoryId', (int) $categoryId);
        }

        if ($minPrice !== null && $minPrice !== '' && is_numeric((string) $minPrice)) {
            $qb->andWhere('COALESCE(p.salePrice, p.price) >= :minPrice')->setParameter('minPrice', (int) $minPrice);
        }

        if ($maxPrice !== null && $maxPrice !== '' && is_numeric((string) $maxPrice)) {
            $qb->andWhere('COALESCE(p.salePrice, p.price) <= :maxPrice')->setParameter('maxPrice', (int) $maxPrice);
        }

        if ($sort === 'price-asc') {
            $qb->addSelect('CASE WHEN p.salePrice IS NULL THEN p.price ELSE p.salePrice END AS HIDDEN effectivePrice')
                ->orderBy('effectivePrice', 'ASC');
        } elseif ($sort === 'price-desc') {
            $qb->addSelect('CASE WHEN p.salePrice IS NULL THEN p.price ELSE p.salePrice END AS HIDDEN effectivePrice')
                ->orderBy('effectivePrice', 'DESC');
        } elseif ($sort === 'name') {
            $qb->orderBy('p.name', 'ASC');
        } elseif ($sort === 'newest') {
            $qb->orderBy('p.createdAt', 'DESC');
        } elseif ($sort === 'popular') {
            $qb->orderBy('p.seenCount', 'DESC')
                ->addOrderBy('p.createdAt', 'DESC');
        } else {
            $qb->orderBy('p.updatedAt', 'DESC');
        }

        $countQb = clone $qb;
        $countQb->resetDQLPart('orderBy');
        $total = (int) $countQb->select('COUNT(DISTINCT p.id)')->getQuery()->getSingleScalarResult();

        $items = $qb->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return new JsonResponse([
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'items' => array_map(static fn (Product $product) => ProductDataMapper::toArray($product), $items),
        ]);
    }

    #[Route('/products/{id}', name: 'api_store_product', methods: ['GET'])]
    public function product(Product $product, EntityManagerInterface $entityManager): JsonResponse
    {
        if (!$product->isActive()) {
            return new JsonResponse(['error' => 'Produit indisponible'], 404);
        }

        $product->incrementSeenCount();
        $entityManager->flush();

        return new JsonResponse(ProductDataMapper::toArray($product));
    }
}
