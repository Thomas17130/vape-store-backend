<?php

namespace App\Controller;

use App\Entity\Box;
use App\Entity\Eliquid;
use App\Entity\Product;
use App\Repository\BrandRepository;
use App\Repository\ProductRepository;
use App\Security\RequestAuth;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/products')]
class ProductController extends AbstractController
{
    public function __construct(private readonly RequestAuth $requestAuth)
    {
    }

    #[Route('', name: 'api_products_list', methods: ['GET'])]
    public function list(Request $request, ProductRepository $productRepository): JsonResponse
    {
        $search = trim((string) $request->query->get('search', ''));
        $type = trim((string) $request->query->get('type', ''));
        $brandIdRaw = $request->query->get('brandId');
        $brandId = null;

        if ($brandIdRaw !== null && $brandIdRaw !== '') {
            if (!is_numeric((string) $brandIdRaw)) {
                return new JsonResponse(['error' => 'brandId doit etre numerique'], Response::HTTP_BAD_REQUEST);
            }

            $brandId = (int) $brandIdRaw;
        }

        $products = $productRepository->findByFilters($search, $type, $brandId);
        $data = array_map(fn (Product $product) => $this->toProductArray($product), $products);

        return new JsonResponse($data);
    }

    #[Route('/{id}', name: 'api_products_show', methods: ['GET'])]
    public function show(Product $product): JsonResponse
    {
        return new JsonResponse($this->toProductArray($product));
    }

    #[Route('', name: 'api_products_create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $entityManager,
        BrandRepository $brandRepository
    ): JsonResponse {
        if (($guard = $this->requireAdmin($request)) !== null) {
            return $guard;
        }

        $payload = $this->readPayload($request);
        if ($payload === null) {
            return new JsonResponse(['error' => 'Corps JSON invalide'], Response::HTTP_BAD_REQUEST);
        }

        $product = $this->buildProductFromPayload($payload);
        $validation = $this->hydrateCommonFields($product, $payload, $brandRepository);
        if ($validation !== null) {
            return $validation;
        }

        $subtypeValidation = $this->hydrateSubtypeFields($product, $payload);
        if ($subtypeValidation !== null) {
            return $subtypeValidation;
        }

        if ($product->getSku() === null || trim((string) $product->getSku()) === '') {
            $product->setSku($this->generateSku($product->getName() ?? 'product'));
        }

        if ($product->getSlug() === null || trim((string) $product->getSlug()) === '') {
            $product->setSlug($this->slugify($product->getName() ?? 'product').'-'.strtolower(bin2hex(random_bytes(2))));
        }

        $entityManager->persist($product);
        $entityManager->flush();

        return new JsonResponse($this->toProductArray($product), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_products_update', methods: ['PUT', 'PATCH'])]
    public function update(
        Product $product,
        Request $request,
        EntityManagerInterface $entityManager,
        BrandRepository $brandRepository
    ): JsonResponse {
        if (($guard = $this->requireAdmin($request)) !== null) {
            return $guard;
        }

        $payload = $this->readPayload($request);
        if ($payload === null) {
            return new JsonResponse(['error' => 'Corps JSON invalide'], Response::HTTP_BAD_REQUEST);
        }

        $validation = $this->hydrateCommonFields($product, $payload, $brandRepository, true);
        if ($validation !== null) {
            return $validation;
        }

        $subtypeValidation = $this->hydrateSubtypeFields($product, $payload, true);
        if ($subtypeValidation !== null) {
            return $subtypeValidation;
        }

        $entityManager->flush();

        return new JsonResponse($this->toProductArray($product));
    }

    #[Route('/{id}', name: 'api_products_delete', methods: ['DELETE'])]
    public function delete(Request $request, Product $product, EntityManagerInterface $entityManager): JsonResponse
    {
        if (($guard = $this->requireAdmin($request)) !== null) {
            return $guard;
        }

        $entityManager->remove($product);
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

    private function buildProductFromPayload(array $payload): Product
    {
        $type = isset($payload['type']) ? strtolower(trim((string) $payload['type'])) : '';

        if ($type === 'box') {
            return new Box();
        }

        if ($type === 'e-liquid' || $type === 'eliquid') {
            return new Eliquid();
        }

        return new Product();
    }

    private function hydrateCommonFields(
        Product $product,
        array $payload,
        BrandRepository $brandRepository,
        bool $partial = false
    ): ?JsonResponse {
        if (array_key_exists('name', $payload) || !$partial) {
            $name = isset($payload['name']) ? trim((string) $payload['name']) : '';
            if ($name === '') {
                return new JsonResponse(['error' => 'name est requis'], Response::HTTP_BAD_REQUEST);
            }

            $product->setName($name);
        }

        if (array_key_exists('description', $payload) || !$partial) {
            $description = isset($payload['description']) ? trim((string) $payload['description']) : '';
            if ($description === '') {
                return new JsonResponse(['error' => 'description est requis'], Response::HTTP_BAD_REQUEST);
            }

            $product->setDescription($description);
        }

        if (array_key_exists('quantity', $payload) || !$partial) {
            if (!isset($payload['quantity']) || !is_numeric($payload['quantity'])) {
                return new JsonResponse(['error' => 'quantity doit etre numerique'], Response::HTTP_BAD_REQUEST);
            }

            $product->setQuantity((int) $payload['quantity']);
        }

        if (array_key_exists('price', $payload) || !$partial) {
            if (!isset($payload['price']) || !is_numeric($payload['price'])) {
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

        if (array_key_exists('isActive', $payload)) {
            $product->setIsActive((bool) $payload['isActive']);
        }

        if (array_key_exists('sku', $payload)) {
            $sku = trim((string) $payload['sku']);
            if ($sku === '') {
                return new JsonResponse(['error' => 'sku ne peut pas etre vide'], Response::HTTP_BAD_REQUEST);
            }
            $product->setSku($sku);
        }

        if (array_key_exists('slug', $payload)) {
            $slug = trim((string) $payload['slug']);
            if ($slug === '') {
                return new JsonResponse(['error' => 'slug ne peut pas etre vide'], Response::HTTP_BAD_REQUEST);
            }
            $product->setSlug($slug);
        }

        if (array_key_exists('brand_id', $payload)) {
            if ($payload['brand_id'] === null || $payload['brand_id'] === '') {
                $product->setBrand(null);
            } else {
                if (!is_numeric($payload['brand_id'])) {
                    return new JsonResponse(['error' => 'brand_id doit etre numerique'], Response::HTTP_BAD_REQUEST);
                }

                $brand = $brandRepository->find((int) $payload['brand_id']);
                if ($brand === null) {
                    return new JsonResponse(['error' => 'Marque introuvable'], Response::HTTP_BAD_REQUEST);
                }

                $product->setBrand($brand);
            }
        }

        return null;
    }

    private function hydrateSubtypeFields(Product $product, array $payload, bool $partial = false): ?JsonResponse
    {
        if ($product instanceof Box && (array_key_exists('type_battery', $payload) || !$partial)) {
            if (!isset($payload['type_battery']) || !is_numeric($payload['type_battery'])) {
                return new JsonResponse(['error' => 'type_battery doit etre numerique'], Response::HTTP_BAD_REQUEST);
            }

            $product->setTypeBattery((int) $payload['type_battery']);
        }

        if ($product instanceof Eliquid && (array_key_exists('volume', $payload) || !$partial)) {
            if (!isset($payload['volume']) || !is_numeric($payload['volume'])) {
                return new JsonResponse(['error' => 'volume doit etre numerique'], Response::HTTP_BAD_REQUEST);
            }

            $product->setVolume((int) $payload['volume']);
        }

        return null;
    }

    private function toProductArray(Product $product): array
    {
        $data = [
            'id' => $product->getId(),
            'name' => $product->getName(),
            'quantity' => $product->getQuantity(),
            'description' => $product->getDescription(),
            'price' => $product->getPrice(),
            'salePrice' => $product->getSalePrice(),
            'sku' => $product->getSku(),
            'slug' => $product->getSlug(),
            'isActive' => $product->isActive(),
            'seenCount' => $product->getSeenCount(),
            'type' => $this->resolveType($product),
            'brand' => $product->getBrand() ? [
                'id' => $product->getBrand()->getId(),
                'name' => $product->getBrand()->getName(),
            ] : null,
        ];

        if ($product instanceof Box) {
            $data['type_battery'] = $product->getTypeBattery();
        }

        if ($product instanceof Eliquid) {
            $data['volume'] = $product->getVolume();
        }

        return $data;
    }

    private function resolveType(Product $product): string
    {
        if ($product instanceof Box) {
            return 'box';
        }

        if ($product instanceof Eliquid) {
            return 'e-liquid';
        }

        return 'product';
    }

    private function generateSku(string $name): string
    {
        $seed = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $name), 0, 6));
        if ($seed === '') {
            $seed = 'PRD';
        }

        return $seed.'-'.strtoupper(bin2hex(random_bytes(3)));
    }

    private function slugify(string $name): string
    {
        $slug = mb_strtolower($name);
        $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug) ?? '';

        return trim($slug, '-');
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
