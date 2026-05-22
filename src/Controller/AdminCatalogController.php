<?php

namespace App\Controller;

use App\Entity\Box;
use App\Entity\Category;
use App\Entity\Eliquid;
use App\Entity\InventoryMovement;
use App\Entity\Product;
use App\Entity\ProductImage;
use App\Entity\ProductVariant;
use App\Repository\BrandRepository;
use App\Repository\CategoryRepository;
use App\Repository\ProductImageRepository;
use App\Repository\ProductRepository;
use App\Repository\ProductVariantRepository;
use App\Security\RequestAuth;
use App\Support\ProductDataMapper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/catalog')]
class AdminCatalogController extends AbstractController
{
    public function __construct(private readonly RequestAuth $requestAuth)
    {
    }

    #[Route('/products', name: 'api_admin_catalog_products', methods: ['GET'])]
    public function products(Request $request, ProductRepository $productRepository): JsonResponse
    {
        if (($guard = $this->requireAdmin($request)) !== null) {
            return $guard;
        }

        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(100, max(1, (int) $request->query->get('limit', 20)));
        $search = trim((string) $request->query->get('q', ''));
        $type = trim((string) $request->query->get('type', ''));
        $active = $request->query->get('active');

        $qb = $productRepository->createQueryBuilder('p')
            ->leftJoin('p.brand', 'b')->addSelect('b');

        if ($search !== '') {
            $qb->andWhere('LOWER(p.name) LIKE :search OR LOWER(p.description) LIKE :search OR LOWER(p.sku) LIKE :search')
                ->setParameter('search', '%'.mb_strtolower($search).'%');
        }

        if ($type === 'box') {
            $qb->andWhere('p INSTANCE OF App\\Entity\\Box');
        } elseif ($type === 'e-liquid') {
            $qb->andWhere('p INSTANCE OF App\\Entity\\Eliquid');
        } elseif ($type === 'product') {
            $qb->andWhere('p NOT INSTANCE OF App\\Entity\\Box')
                ->andWhere('p NOT INSTANCE OF App\\Entity\\Eliquid');
        }

        if ($active !== null && $active !== '') {
            $value = filter_var($active, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($value !== null) {
                $qb->andWhere('p.isActive = :active')->setParameter('active', $value);
            }
        }

        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(DISTINCT p.id)')->getQuery()->getSingleScalarResult();

        $products = $qb->orderBy('p.updatedAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return new JsonResponse([
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'items' => array_map(static fn (Product $product) => ProductDataMapper::toArray($product), $products),
        ]);
    }

    #[Route('/products/{id}', name: 'api_admin_catalog_product', methods: ['GET'])]
    public function product(Request $request, Product $product): JsonResponse
    {
        if (($guard = $this->requireAdmin($request)) !== null) {
            return $guard;
        }

        return new JsonResponse(ProductDataMapper::toArray($product));
    }

    #[Route('/products', name: 'api_admin_catalog_product_create', methods: ['POST'])]
    public function createProduct(
        Request $request,
        EntityManagerInterface $entityManager,
        BrandRepository $brandRepository,
        CategoryRepository $categoryRepository,
        ProductRepository $productRepository
    ): JsonResponse {
        if (($guard = $this->requireAdmin($request)) !== null) {
            return $guard;
        }

        $payload = $this->readPayload($request);
        if ($payload === null) {
            return new JsonResponse(['error' => 'Corps JSON invalide'], Response::HTTP_BAD_REQUEST);
        }

        $type = strtolower(trim((string) ($payload['type'] ?? 'product')));
        $product = $this->instantiateProductByType($type);
        $error = $this->hydrateProduct($product, $payload, $brandRepository, $categoryRepository, $productRepository);
        if ($error !== null) {
            return $error;
        }

        $entityManager->persist($product);
        $entityManager->flush();

        return new JsonResponse(ProductDataMapper::toArray($product), Response::HTTP_CREATED);
    }

    #[Route('/products/{id}', name: 'api_admin_catalog_product_update', methods: ['PUT', 'PATCH'])]
    public function updateProduct(
        Product $product,
        Request $request,
        EntityManagerInterface $entityManager,
        BrandRepository $brandRepository,
        CategoryRepository $categoryRepository,
        ProductRepository $productRepository
    ): JsonResponse {
        if (($guard = $this->requireAdmin($request)) !== null) {
            return $guard;
        }

        $payload = $this->readPayload($request);
        if ($payload === null) {
            return new JsonResponse(['error' => 'Corps JSON invalide'], Response::HTTP_BAD_REQUEST);
        }

        $error = $this->hydrateProduct($product, $payload, $brandRepository, $categoryRepository, $productRepository, true);
        if ($error !== null) {
            return $error;
        }

        $entityManager->flush();

        return new JsonResponse(ProductDataMapper::toArray($product));
    }

    #[Route('/products/{id}', name: 'api_admin_catalog_product_delete', methods: ['DELETE'])]
    public function deleteProduct(Request $request, Product $product, EntityManagerInterface $entityManager): JsonResponse
    {
        if (($guard = $this->requireAdmin($request)) !== null) {
            return $guard;
        }

        $entityManager->remove($product);
        $entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/products/{id}/images', name: 'api_admin_catalog_product_image_create', methods: ['POST'])]
    public function addProductImage(Product $product, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        if (($guard = $this->requireAdmin($request)) !== null) {
            return $guard;
        }

        $uploadedFile = $request->files->get('image');
        $payload = [];

        if ($uploadedFile !== null) {
            $payload = $request->request->all();
        } else {
            $payload = $this->readPayload($request);
            if ($payload === null) {
                return new JsonResponse(['error' => 'Corps JSON invalide'], Response::HTTP_BAD_REQUEST);
            }
        }

        $url = $uploadedFile === null ? trim((string) ($payload['url'] ?? '')) : $this->storeUploadedProductImage($uploadedFile);
        if ($url === '') {
            return new JsonResponse(['error' => 'url est requis'], Response::HTTP_BAD_REQUEST);
        }

        $image = new ProductImage();
        $image->setProduct($product);
        $image->setUrl($url);
        $image->setAltText(isset($payload['altText']) ? trim((string) $payload['altText']) : null);
        $image->setPosition((int) ($payload['position'] ?? 0));
        $image->setIsPrimary((bool) ($payload['isPrimary'] ?? false));

        if ($image->isPrimary()) {
            foreach ($product->getImages() as $existing) {
                $existing->setIsPrimary(false);
            }
        }

        $entityManager->persist($image);
        $entityManager->flush();

        return new JsonResponse(ProductDataMapper::toArray($product), Response::HTTP_CREATED);
    }

    #[Route('/products/{id}/images/{imageId}', name: 'api_admin_catalog_product_image_delete', methods: ['DELETE'])]
    public function deleteProductImage(
        Request $request,
        Product $product,
        int $imageId,
        ProductImageRepository $imageRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        if (($guard = $this->requireAdmin($request)) !== null) {
            return $guard;
        }

        $image = $imageRepository->find($imageId);
        if ($image === null || $image->getProduct()?->getId() !== $product->getId()) {
            return new JsonResponse(['error' => 'Image introuvable'], Response::HTTP_NOT_FOUND);
        }

        $entityManager->remove($image);
        $entityManager->flush();

        return new JsonResponse(ProductDataMapper::toArray($product));
    }

    #[Route('/products/{id}/variants', name: 'api_admin_catalog_product_variant_create', methods: ['POST'])]
    public function addVariant(
        Product $product,
        Request $request,
        EntityManagerInterface $entityManager,
        ProductVariantRepository $variantRepository
    ): JsonResponse {
        if (($guard = $this->requireAdmin($request)) !== null) {
            return $guard;
        }

        $payload = $this->readPayload($request);
        if ($payload === null) {
            return new JsonResponse(['error' => 'Corps JSON invalide'], Response::HTTP_BAD_REQUEST);
        }

        $sku = trim((string) ($payload['sku'] ?? ''));
        $title = trim((string) ($payload['title'] ?? ''));
        $price = $payload['price'] ?? null;
        $quantity = $payload['quantity'] ?? null;

        if ($sku === '' || $title === '' || !is_numeric($price) || !is_numeric($quantity)) {
            return new JsonResponse(['error' => 'sku, titre, prix et quantite sont requis'], Response::HTTP_BAD_REQUEST);
        }

        if ($variantRepository->findOneBy(['sku' => $sku]) !== null) {
            return new JsonResponse(['error' => 'Le SKU de la variante existe deja'], Response::HTTP_CONFLICT);
        }

        $variant = new ProductVariant();
        $variant->setProduct($product);
        $variant->setSku($sku);
        $variant->setTitle($title);
        $variant->setPrice((int) $price);
        $variant->setQuantity((int) $quantity);
        $variant->setAttributes(is_array($payload['attributes'] ?? null) ? $payload['attributes'] : []);
        $variant->setIsDefault((bool) ($payload['isDefault'] ?? false));

        if ($variant->isDefault()) {
            foreach ($product->getVariants() as $existing) {
                $existing->setIsDefault(false);
            }
        }

        $entityManager->persist($variant);
        $entityManager->flush();

        return new JsonResponse(ProductDataMapper::toArray($product), Response::HTTP_CREATED);
    }

    #[Route('/variants/{variantId}', name: 'api_admin_catalog_variant_update', methods: ['PUT', 'PATCH'])]
    public function updateVariant(
        int $variantId,
        Request $request,
        ProductVariantRepository $variantRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        if (($guard = $this->requireAdmin($request)) !== null) {
            return $guard;
        }

        $variant = $variantRepository->find($variantId);
        if ($variant === null) {
            return new JsonResponse(['error' => 'Variante introuvable'], Response::HTTP_NOT_FOUND);
        }

        $payload = $this->readPayload($request);
        if ($payload === null) {
            return new JsonResponse(['error' => 'Corps JSON invalide'], Response::HTTP_BAD_REQUEST);
        }

        if (isset($payload['title'])) {
            $title = trim((string) $payload['title']);
            if ($title === '') {
                return new JsonResponse(['error' => 'title ne peut pas etre vide'], Response::HTTP_BAD_REQUEST);
            }
            $variant->setTitle($title);
        }

        if (isset($payload['price'])) {
            if (!is_numeric($payload['price'])) {
                return new JsonResponse(['error' => 'price doit etre numerique'], Response::HTTP_BAD_REQUEST);
            }
            $variant->setPrice((int) $payload['price']);
        }

        if (isset($payload['quantity'])) {
            if (!is_numeric($payload['quantity'])) {
                return new JsonResponse(['error' => 'quantity doit etre numerique'], Response::HTTP_BAD_REQUEST);
            }
            $variant->setQuantity((int) $payload['quantity']);
        }

        if (array_key_exists('attributes', $payload)) {
            if ($payload['attributes'] !== null && !is_array($payload['attributes'])) {
                return new JsonResponse(['error' => 'attributes doit etre un tableau'], Response::HTTP_BAD_REQUEST);
            }
            $variant->setAttributes($payload['attributes']);
        }

        if (isset($payload['isDefault'])) {
            $variant->setIsDefault((bool) $payload['isDefault']);
            if ($variant->isDefault()) {
                foreach ($variant->getProduct()?->getVariants() ?? [] as $existing) {
                    if ($existing->getId() !== $variant->getId()) {
                        $existing->setIsDefault(false);
                    }
                }
            }
        }

        $entityManager->flush();

        return new JsonResponse(ProductDataMapper::toArray($variant->getProduct()));
    }

    #[Route('/variants/{variantId}', name: 'api_admin_catalog_variant_delete', methods: ['DELETE'])]
    public function deleteVariant(Request $request, int $variantId, ProductVariantRepository $variantRepository, EntityManagerInterface $entityManager): JsonResponse
    {
        if (($guard = $this->requireAdmin($request)) !== null) {
            return $guard;
        }

        $variant = $variantRepository->find($variantId);
        if ($variant === null) {
            return new JsonResponse(['error' => 'Variante introuvable'], Response::HTTP_NOT_FOUND);
        }

        $product = $variant->getProduct();
        $entityManager->remove($variant);
        $entityManager->flush();

        return new JsonResponse($product ? ProductDataMapper::toArray($product) : ['ok' => true]);
    }

    #[Route('/products/{id}/inventory-adjustments', name: 'api_admin_catalog_product_inventory_adjustment', methods: ['POST'])]
    public function adjustInventory(
        Product $product,
        Request $request,
        ProductVariantRepository $variantRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        if (($guard = $this->requireAdmin($request)) !== null) {
            return $guard;
        }

        $payload = $this->readPayload($request);
        if ($payload === null) {
            return new JsonResponse(['error' => 'Corps JSON invalide'], Response::HTTP_BAD_REQUEST);
        }

        $quantityChange = $payload['quantityChange'] ?? null;
        $reason = trim((string) ($payload['reason'] ?? 'manual-adjustment'));
        $variantId = $payload['variantId'] ?? null;

        if (!is_numeric($quantityChange) || (int) $quantityChange === 0) {
            return new JsonResponse(['error' => 'quantityChange doit etre une valeur numerique non nulle'], Response::HTTP_BAD_REQUEST);
        }

        $variant = null;
        if ($variantId !== null && $variantId !== '') {
            if (!is_numeric($variantId)) {
                return new JsonResponse(['error' => 'variantId doit etre numerique'], Response::HTTP_BAD_REQUEST);
            }

            $variant = $variantRepository->find((int) $variantId);
            if ($variant === null || $variant->getProduct()?->getId() !== $product->getId()) {
                return new JsonResponse(['error' => 'Variante introuvable pour ce produit'], Response::HTTP_BAD_REQUEST);
            }

            $variant->setQuantity(max(0, (int) $variant->getQuantity() + (int) $quantityChange));
        }

        $product->setQuantity(max(0, (int) $product->getQuantity() + (int) $quantityChange));

        $movement = new InventoryMovement();
        $movement->setProduct($product);
        $movement->setVariant($variant);
        $movement->setQuantityChange((int) $quantityChange);
        $movement->setReason($reason === '' ? 'manual-adjustment' : $reason);

        $entityManager->persist($movement);
        $entityManager->flush();

        return new JsonResponse(ProductDataMapper::toArray($product));
    }

    #[Route('/categories', name: 'api_admin_catalog_categories', methods: ['GET'])]
    public function categories(Request $request, CategoryRepository $categoryRepository): JsonResponse
    {
        if (($guard = $this->requireAdmin($request)) !== null) {
            return $guard;
        }

        $items = $categoryRepository->findBy([], ['name' => 'ASC']);

        return new JsonResponse(array_map(static fn (Category $category) => [
            'id' => $category->getId(),
            'name' => $category->getName(),
            'slug' => $category->getSlug(),
            'parentId' => $category->getParent()?->getId(),
        ], $items));
    }

    #[Route('/categories', name: 'api_admin_catalog_category_create', methods: ['POST'])]
    public function createCategory(
        Request $request,
        CategoryRepository $categoryRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        if (($guard = $this->requireAdmin($request)) !== null) {
            return $guard;
        }

        $payload = $this->readPayload($request);
        if ($payload === null) {
            return new JsonResponse(['error' => 'Corps JSON invalide'], Response::HTTP_BAD_REQUEST);
        }

        $name = trim((string) ($payload['name'] ?? ''));
        if ($name === '') {
            return new JsonResponse(['error' => 'name est requis'], Response::HTTP_BAD_REQUEST);
        }

        $slug = trim((string) ($payload['slug'] ?? $this->slugify($name)));
        if ($slug === '') {
            return new JsonResponse(['error' => 'slug est requis'], Response::HTTP_BAD_REQUEST);
        }

        if ($categoryRepository->findOneBy(['slug' => $slug]) !== null) {
            return new JsonResponse(['error' => 'Le slug existe deja'], Response::HTTP_CONFLICT);
        }

        $category = new Category();
        $category->setName($name);
        $category->setSlug($slug);

        if (isset($payload['parentId']) && $payload['parentId'] !== null && $payload['parentId'] !== '') {
            if (!is_numeric($payload['parentId'])) {
                return new JsonResponse(['error' => 'parentId doit etre numerique'], Response::HTTP_BAD_REQUEST);
            }

            $parent = $categoryRepository->find((int) $payload['parentId']);
            if ($parent === null) {
                return new JsonResponse(['error' => 'Categorie parente introuvable'], Response::HTTP_BAD_REQUEST);
            }
            $category->setParent($parent);
        }

        $entityManager->persist($category);
        $entityManager->flush();

        return new JsonResponse([
            'id' => $category->getId(),
            'name' => $category->getName(),
            'slug' => $category->getSlug(),
            'parentId' => $category->getParent()?->getId(),
        ], Response::HTTP_CREATED);
    }

    #[Route('/categories/{id}', name: 'api_admin_catalog_category_update', methods: ['PUT', 'PATCH'])]
    public function updateCategory(
        Category $category,
        Request $request,
        CategoryRepository $categoryRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        if (($guard = $this->requireAdmin($request)) !== null) {
            return $guard;
        }

        $payload = $this->readPayload($request);
        if ($payload === null) {
            return new JsonResponse(['error' => 'Corps JSON invalide'], Response::HTTP_BAD_REQUEST);
        }

        if (isset($payload['name'])) {
            $name = trim((string) $payload['name']);
            if ($name === '') {
                return new JsonResponse(['error' => 'name ne peut pas etre vide'], Response::HTTP_BAD_REQUEST);
            }
            $category->setName($name);
        }

        if (isset($payload['slug'])) {
            $slug = trim((string) $payload['slug']);
            if ($slug === '') {
                return new JsonResponse(['error' => 'slug ne peut pas etre vide'], Response::HTTP_BAD_REQUEST);
            }
            $existing = $categoryRepository->findOneBy(['slug' => $slug]);
            if ($existing !== null && $existing->getId() !== $category->getId()) {
                return new JsonResponse(['error' => 'Le slug existe deja'], Response::HTTP_CONFLICT);
            }
            $category->setSlug($slug);
        }

        if (array_key_exists('parentId', $payload)) {
            if ($payload['parentId'] === null || $payload['parentId'] === '') {
                $category->setParent(null);
            } else {
                if (!is_numeric($payload['parentId'])) {
                    return new JsonResponse(['error' => 'parentId doit etre numerique'], Response::HTTP_BAD_REQUEST);
                }
                $parent = $categoryRepository->find((int) $payload['parentId']);
                if ($parent === null) {
                    return new JsonResponse(['error' => 'Categorie parente introuvable'], Response::HTTP_BAD_REQUEST);
                }
                if ($parent->getId() === $category->getId()) {
                    return new JsonResponse(['error' => 'Une categorie ne peut pas etre sa propre parente'], Response::HTTP_BAD_REQUEST);
                }
                $category->setParent($parent);
            }
        }

        $entityManager->flush();

        return new JsonResponse([
            'id' => $category->getId(),
            'name' => $category->getName(),
            'slug' => $category->getSlug(),
            'parentId' => $category->getParent()?->getId(),
        ]);
    }

    #[Route('/categories/{id}', name: 'api_admin_catalog_category_delete', methods: ['DELETE'])]
    public function deleteCategory(Request $request, Category $category, EntityManagerInterface $entityManager): JsonResponse
    {
        if (($guard = $this->requireAdmin($request)) !== null) {
            return $guard;
        }

        $entityManager->remove($category);
        $entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function readPayload(Request $request): ?array
    {
        try {
            return $request->toArray();
        } catch (\JsonException) {
            return null;
        }
    }

    private function storeUploadedProductImage($uploadedFile): string
    {
        $extension = $uploadedFile->guessExtension() ?: $uploadedFile->getClientOriginalExtension() ?: 'bin';
        $extension = strtolower(preg_replace('/[^a-z0-9]+/i', '', $extension) ?: 'bin');
        $filename = sprintf('%s.%s', bin2hex(random_bytes(12)), $extension);
        $uploadDir = $this->getParameter('kernel.project_dir').'/public/uploads/products';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        $uploadedFile->move($uploadDir, $filename);

        return '/uploads/products/'.$filename;
    }

    private function instantiateProductByType(string $type): Product
    {
        if ($type === 'box') {
            return new Box();
        }

        if ($type === 'e-liquid' || $type === 'eliquid') {
            return new Eliquid();
        }

        return new Product();
    }

    private function hydrateProduct(
        Product $product,
        array $payload,
        BrandRepository $brandRepository,
        CategoryRepository $categoryRepository,
        ProductRepository $productRepository,
        bool $partial = false
    ): ?JsonResponse {
        $required = ['name', 'description', 'price', 'quantity'];

        foreach ($required as $field) {
            if (!$partial || array_key_exists($field, $payload)) {
                if (!isset($payload[$field]) || trim((string) $payload[$field]) === '') {
                    return new JsonResponse(['error' => sprintf('%s est requis', $field)], Response::HTTP_BAD_REQUEST);
                }
            }
        }

        if (array_key_exists('name', $payload) || !$partial) {
            $product->setName(trim((string) ($payload['name'] ?? '')));
        }

        if (array_key_exists('description', $payload) || !$partial) {
            $product->setDescription(trim((string) ($payload['description'] ?? '')));
        }

        if (array_key_exists('price', $payload) || !$partial) {
            if (!is_numeric($payload['price'] ?? null)) {
                return new JsonResponse(['error' => 'price doit etre numerique'], Response::HTTP_BAD_REQUEST);
            }
            $product->setPrice((int) $payload['price']);
        }

        if (array_key_exists('salePrice', $payload)) {
            if ($payload['salePrice'] === null || $payload['salePrice'] === '') {
                $product->setSalePrice(null);
            } else {
                if (!is_numeric($payload['salePrice'])) {
                    return new JsonResponse(['error' => 'salePrice doit etre numerique'], Response::HTTP_BAD_REQUEST);
                }
                $product->setSalePrice((int) $payload['salePrice']);
            }
        }

        if (array_key_exists('quantity', $payload) || !$partial) {
            if (!is_numeric($payload['quantity'] ?? null)) {
                return new JsonResponse(['error' => 'quantity doit etre numerique'], Response::HTTP_BAD_REQUEST);
            }
            $product->setQuantity(max(0, (int) $payload['quantity']));
        }

        if (array_key_exists('isActive', $payload)) {
            $product->setIsActive((bool) $payload['isActive']);
        }

        if (array_key_exists('sku', $payload) || !$partial) {
            $sku = trim((string) ($payload['sku'] ?? ''));
            if ($sku === '') {
                $sku = $this->generateSku($product);
            }

            $existingSku = $productRepository->findOneBy(['sku' => $sku]);
            if ($existingSku !== null && $existingSku->getId() !== $product->getId()) {
                return new JsonResponse(['error' => 'Le SKU existe deja'], Response::HTTP_CONFLICT);
            }

            $product->setSku($sku);
        }

        if (array_key_exists('slug', $payload) || !$partial) {
            $slugSource = trim((string) ($payload['slug'] ?? ''));
            $slug = $slugSource !== '' ? $slugSource : $this->slugify((string) $product->getName());

            $existingSlug = $productRepository->findOneBy(['slug' => $slug]);
            if ($existingSlug !== null && $existingSlug->getId() !== $product->getId()) {
                return new JsonResponse(['error' => 'Le slug existe deja'], Response::HTTP_CONFLICT);
            }

            $product->setSlug($slug);
        }

        if (array_key_exists('brandId', $payload) || array_key_exists('brand_id', $payload)) {
            $brandValue = $payload['brandId'] ?? $payload['brand_id'] ?? null;
            if ($brandValue === null || $brandValue === '') {
                $product->setBrand(null);
            } else {
                if (!is_numeric($brandValue)) {
                    return new JsonResponse(['error' => 'brandId doit etre numerique'], Response::HTTP_BAD_REQUEST);
                }
                $brand = $brandRepository->find((int) $brandValue);
                if ($brand === null) {
                    return new JsonResponse(['error' => 'Marque introuvable'], Response::HTTP_BAD_REQUEST);
                }
                $product->setBrand($brand);
            }
        }

        if (array_key_exists('categoryIds', $payload)) {
            if (!is_array($payload['categoryIds'])) {
                return new JsonResponse(['error' => 'categoryIds doit etre un tableau'], Response::HTTP_BAD_REQUEST);
            }

            $product->clearCategories();
            foreach ($payload['categoryIds'] as $categoryId) {
                if (!is_numeric($categoryId)) {
                    return new JsonResponse(['error' => 'categoryIds doit contenir des ID numeriques'], Response::HTTP_BAD_REQUEST);
                }

                $category = $categoryRepository->find((int) $categoryId);
                if ($category === null) {
                    return new JsonResponse(['error' => sprintf('Categorie %s introuvable', $categoryId)], Response::HTTP_BAD_REQUEST);
                }

                $product->addCategory($category);
            }
        }

        if ($product instanceof Box && array_key_exists('type_battery', $payload)) {
            if (!is_numeric($payload['type_battery'])) {
                return new JsonResponse(['error' => 'type_battery doit etre numerique'], Response::HTTP_BAD_REQUEST);
            }
            $product->setTypeBattery((int) $payload['type_battery']);
        }

        if ($product instanceof Eliquid && array_key_exists('volume', $payload)) {
            if (!is_numeric($payload['volume'])) {
                return new JsonResponse(['error' => 'volume doit etre numerique'], Response::HTTP_BAD_REQUEST);
            }
            $product->setVolume((int) $payload['volume']);
        }

        return null;
    }

    private function generateSku(Product $product): string
    {
        $seed = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', (string) $product->getName()), 0, 6));
        if ($seed === '') {
            $seed = 'PRD';
        }

        return $seed.'-'.strtoupper(bin2hex(random_bytes(3)));
    }

    private function slugify(string $text): string
    {
        $slug = mb_strtolower($text);
        $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug) ?? '';
        $slug = trim($slug, '-');

        return $slug;
    }

    private function requireAdmin(Request $request): ?JsonResponse
    {
        $user = $this->requestAuth->resolveUser($request);
        if (!$this->requestAuth->isAdmin($user)) {
            return new JsonResponse(['error' => 'Acces reserve aux administrateurs'], Response::HTTP_FORBIDDEN);
        }

        return null;
    }
}
