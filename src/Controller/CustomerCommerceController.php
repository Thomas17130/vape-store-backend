<?php

namespace App\Controller;

use App\Entity\CustomerAddress;
use App\Entity\Product;
use App\Entity\ProductVariant;
use App\Entity\StoreOrder;
use App\Entity\StoreOrderItem;
use App\Entity\User;
use App\Entity\WishlistItem;
use App\Repository\CustomerAddressRepository;
use App\Repository\ProductRepository;
use App\Repository\ProductVariantRepository;
use App\Repository\StoreOrderRepository;
use App\Repository\WishlistItemRepository;
use App\Security\RequestAuth;
use App\Support\ProductDataMapper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/store')]
class CustomerCommerceController extends AbstractController
{
    public function __construct(private readonly RequestAuth $requestAuth)
    {
    }

    #[Route('/wishlist', name: 'api_store_wishlist', methods: ['GET'])]
    public function wishlist(Request $request, WishlistItemRepository $wishlistItemRepository): JsonResponse
    {
        $user = $this->requestAuth->resolveUser($request);
        if ($user === null) {
            return new JsonResponse(['error' => 'Authentification requise'], Response::HTTP_UNAUTHORIZED);
        }

        $items = $wishlistItemRepository->findBy(['user' => $user], ['createdAt' => 'DESC']);

        return new JsonResponse(array_map(static fn (WishlistItem $item) => [
            'id' => $item->getId(),
            'createdAt' => $item->getCreatedAt()?->format(DATE_ATOM),
            'product' => ProductDataMapper::toArray($item->getProduct()),
        ], $items));
    }

    #[Route('/wishlist', name: 'api_store_wishlist_add', methods: ['POST'])]
    public function addWishlist(
        Request $request,
        ProductRepository $productRepository,
        WishlistItemRepository $wishlistItemRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $user = $this->requestAuth->resolveUser($request);
        if ($user === null) {
            return new JsonResponse(['error' => 'Authentification requise'], Response::HTTP_UNAUTHORIZED);
        }

        $payload = $this->readPayload($request);
        if ($payload === null) {
            return new JsonResponse(['error' => 'Corps JSON invalide'], Response::HTTP_BAD_REQUEST);
        }

        $productId = $payload['productId'] ?? null;

        if (!is_numeric((string) $productId)) {
            return new JsonResponse(['error' => 'productId est requis'], Response::HTTP_BAD_REQUEST);
        }

        $product = $productRepository->find((int) $productId);
        if ($product === null) {
            return new JsonResponse(['error' => 'Produit introuvable'], Response::HTTP_BAD_REQUEST);
        }

        $existing = $wishlistItemRepository->findOneBy(['user' => $user, 'product' => $product]);
        if ($existing !== null) {
            return new JsonResponse(['id' => $existing->getId(), 'alreadyExists' => true]);
        }

        $item = new WishlistItem();
        $item->setUser($user);
        $item->setProduct($product);

        $entityManager->persist($item);
        $entityManager->flush();

        return new JsonResponse(['id' => $item->getId()], Response::HTTP_CREATED);
    }

    #[Route('/wishlist/{id}', name: 'api_store_wishlist_remove', methods: ['DELETE'])]
    public function removeWishlist(Request $request, int $id, WishlistItemRepository $wishlistItemRepository, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->requestAuth->resolveUser($request);
        if ($user === null) {
            return new JsonResponse(['error' => 'Authentification requise'], Response::HTTP_UNAUTHORIZED);
        }

        $item = $wishlistItemRepository->find($id);
        if ($item === null) {
            return new JsonResponse(['error' => 'Article de la liste de souhaits introuvable'], Response::HTTP_NOT_FOUND);
        }

        if ($item->getUser()?->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'Acces refuse'], Response::HTTP_FORBIDDEN);
        }

        $entityManager->remove($item);
        $entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/addresses', name: 'api_store_addresses', methods: ['GET'])]
    public function addresses(Request $request, CustomerAddressRepository $addressRepository): JsonResponse
    {
        $user = $this->requestAuth->resolveUser($request);
        if ($user === null) {
            return new JsonResponse(['error' => 'Authentification requise'], Response::HTTP_UNAUTHORIZED);
        }

        $items = $addressRepository->findBy(['user' => $user], ['isDefault' => 'DESC', 'id' => 'DESC']);

        return new JsonResponse(array_map(fn (CustomerAddress $address) => $this->addressToArray($address), $items));
    }

    #[Route('/addresses', name: 'api_store_addresses_create', methods: ['POST'])]
    public function createAddress(
        Request $request,
        EntityManagerInterface $entityManager,
        CustomerAddressRepository $addressRepository
    ): JsonResponse {
        $user = $this->requestAuth->resolveUser($request);
        if ($user === null) {
            return new JsonResponse(['error' => 'Authentification requise'], Response::HTTP_UNAUTHORIZED);
        }

        $payload = $this->readPayload($request);
        if ($payload === null) {
            return new JsonResponse(['error' => 'Corps JSON invalide'], Response::HTTP_BAD_REQUEST);
        }

        $address = new CustomerAddress();
        $address->setUser($user);

        $error = $this->hydrateAddress($address, $payload, false);
        if ($error !== null) {
            return $error;
        }

        if ($address->isDefault()) {
            $this->unsetDefaultAddresses($user, $addressRepository);
        }

        $entityManager->persist($address);
        $entityManager->flush();

        return new JsonResponse($this->addressToArray($address), Response::HTTP_CREATED);
    }

    #[Route('/addresses/{id}', name: 'api_store_addresses_update', methods: ['PUT', 'PATCH'])]
    public function updateAddress(
        Request $request,
        int $id,
        CustomerAddressRepository $addressRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $user = $this->requestAuth->resolveUser($request);
        if ($user === null) {
            return new JsonResponse(['error' => 'Authentification requise'], Response::HTTP_UNAUTHORIZED);
        }

        $address = $addressRepository->find($id);
        if ($address === null) {
            return new JsonResponse(['error' => 'Adresse introuvable'], Response::HTTP_NOT_FOUND);
        }

        if ($address->getUser()?->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'Acces refuse'], Response::HTTP_FORBIDDEN);
        }

        $payload = $this->readPayload($request);
        if ($payload === null) {
            return new JsonResponse(['error' => 'Corps JSON invalide'], Response::HTTP_BAD_REQUEST);
        }

        $error = $this->hydrateAddress($address, $payload, true);
        if ($error !== null) {
            return $error;
        }

        if ($address->isDefault()) {
            $this->unsetDefaultAddresses($address->getUser(), $addressRepository, $address->getId());
        }

        $entityManager->flush();

        return new JsonResponse($this->addressToArray($address));
    }

    #[Route('/addresses/{id}', name: 'api_store_addresses_delete', methods: ['DELETE'])]
    public function deleteAddress(Request $request, int $id, CustomerAddressRepository $addressRepository, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->requestAuth->resolveUser($request);
        if ($user === null) {
            return new JsonResponse(['error' => 'Authentification requise'], Response::HTTP_UNAUTHORIZED);
        }

        $address = $addressRepository->find($id);
        if ($address === null) {
            return new JsonResponse(['error' => 'Adresse introuvable'], Response::HTTP_NOT_FOUND);
        }

        if ($address->getUser()?->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'Acces refuse'], Response::HTTP_FORBIDDEN);
        }

        $entityManager->remove($address);
        $entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/checkout/quote', name: 'api_store_checkout_quote', methods: ['POST'])]
    public function checkoutQuote(Request $request, ProductRepository $productRepository, ProductVariantRepository $variantRepository): JsonResponse
    {
        $payload = $this->readPayload($request);
        if ($payload === null || !is_array($payload['lines'] ?? null)) {
            return new JsonResponse(['error' => 'Le tableau lines est requis'], Response::HTTP_BAD_REQUEST);
        }

        $quote = $this->buildQuote($payload['lines'], $productRepository, $variantRepository);
        if ($quote['error'] !== null) {
            return new JsonResponse(['error' => $quote['error']], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse($quote['data']);
    }

    #[Route('/checkout/place', name: 'api_store_checkout_place', methods: ['POST'])]
    public function placeOrder(
        Request $request,
        ProductRepository $productRepository,
        ProductVariantRepository $variantRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $user = $this->requestAuth->resolveUser($request);
        if ($user === null) {
            return new JsonResponse(['error' => 'Authentification requise'], Response::HTTP_UNAUTHORIZED);
        }

        $payload = $this->readPayload($request);
        if ($payload === null || !is_array($payload['lines'] ?? null)) {
            return new JsonResponse(['error' => 'Le tableau lines est requis'], Response::HTTP_BAD_REQUEST);
        }

        $shipping = is_array($payload['shipping'] ?? null) ? $payload['shipping'] : [];

        $quote = $this->buildQuote($payload['lines'], $productRepository, $variantRepository);
        if ($quote['error'] !== null) {
            return new JsonResponse(['error' => $quote['error']], Response::HTTP_BAD_REQUEST);
        }

        $data = $quote['data'];
        $order = new StoreOrder();
        $order->setOrderNumber($this->generateOrderNumber());
        $order->setUser($user);
        $order->setStatus('placed');
        $order->setSubtotal((int) $data['subtotal']);
        $order->setShippingCost((int) $data['shippingCost']);
        $order->setTotal((int) $data['total']);
        $order->setShippingSnapshot($shipping);

        foreach ($data['lines'] as $line) {
            $product = $productRepository->find((int) $line['productId']);
            if ($product === null) {
                return new JsonResponse(['error' => 'Produit introuvable lors de l enregistrement de la commande'], Response::HTTP_BAD_REQUEST);
            }

            $variant = null;
            if (($line['variantId'] ?? null) !== null) {
                $variant = $variantRepository->find((int) $line['variantId']);
            }

            $item = new StoreOrderItem();
            $item->setOrder($order);
            $item->setProduct($product);
            $item->setVariant($variant);
            $item->setQuantity((int) $line['quantity']);
            $item->setUnitPrice((int) $line['unitPrice']);
            $item->setTotalPrice((int) $line['lineTotal']);
            $order->addItem($item);

            $product->setQuantity(max(0, (int) $product->getQuantity() - (int) $line['quantity']));
            if ($variant !== null) {
                $variant->setQuantity(max(0, (int) $variant->getQuantity() - (int) $line['quantity']));
            }
        }

        $entityManager->persist($order);
        $entityManager->flush();

        return new JsonResponse($this->orderToArray($order), Response::HTTP_CREATED);
    }

    #[Route('/orders', name: 'api_store_orders', methods: ['GET'])]
    public function orders(Request $request, StoreOrderRepository $orderRepository): JsonResponse
    {
        $user = $this->requestAuth->resolveUser($request);
        if ($user === null) {
            return new JsonResponse(['error' => 'Authentification requise'], Response::HTTP_UNAUTHORIZED);
        }

        $orders = $orderRepository->findBy(['user' => $user], ['createdAt' => 'DESC']);

        return new JsonResponse(array_map(fn (StoreOrder $order) => $this->orderToArray($order), $orders));
    }

    #[Route('/orders/{id}', name: 'api_store_order', methods: ['GET'])]
    public function order(Request $request, int $id, StoreOrderRepository $orderRepository): JsonResponse
    {
        $user = $this->requestAuth->resolveUser($request);
        if ($user === null) {
            return new JsonResponse(['error' => 'Authentification requise'], Response::HTTP_UNAUTHORIZED);
        }

        $order = $orderRepository->find($id);
        if ($order === null) {
            return new JsonResponse(['error' => 'Commande introuvable'], Response::HTTP_NOT_FOUND);
        }

        if ($order->getUser()?->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'Acces refuse'], Response::HTTP_FORBIDDEN);
        }

        return new JsonResponse($this->orderToArray($order));
    }

    private function readPayload(Request $request): ?array
    {
        try {
            return $request->toArray();
        } catch (\JsonException) {
            return null;
        }
    }

    private function hydrateAddress(CustomerAddress $address, array $payload, bool $partial): ?JsonResponse
    {
        $required = ['label', 'recipientName', 'phone', 'line1', 'city', 'postalCode', 'country'];

        foreach ($required as $field) {
            if (!$partial || array_key_exists($field, $payload)) {
                if (!isset($payload[$field]) || trim((string) $payload[$field]) === '') {
                    return new JsonResponse(['error' => sprintf('%s est requis', $field)], Response::HTTP_BAD_REQUEST);
                }
            }
        }

        if (array_key_exists('label', $payload)) {
            $address->setLabel(trim((string) $payload['label']));
        }

        if (array_key_exists('recipientName', $payload)) {
            $address->setRecipientName(trim((string) $payload['recipientName']));
        }

        if (array_key_exists('phone', $payload)) {
            $address->setPhone(trim((string) $payload['phone']));
        }

        if (array_key_exists('line1', $payload)) {
            $address->setLine1(trim((string) $payload['line1']));
        }

        if (array_key_exists('line2', $payload)) {
            $line2 = trim((string) $payload['line2']);
            $address->setLine2($line2 === '' ? null : $line2);
        }

        if (array_key_exists('city', $payload)) {
            $address->setCity(trim((string) $payload['city']));
        }

        if (array_key_exists('postalCode', $payload)) {
            $address->setPostalCode(trim((string) $payload['postalCode']));
        }

        if (array_key_exists('country', $payload)) {
            $address->setCountry(trim((string) $payload['country']));
        }

        if (array_key_exists('isDefault', $payload)) {
            $address->setIsDefault((bool) $payload['isDefault']);
        }

        return null;
    }

    private function unsetDefaultAddresses(?User $user, CustomerAddressRepository $addressRepository, ?int $exceptId = null): void
    {
        if ($user === null) {
            return;
        }

        $addresses = $addressRepository->findBy(['user' => $user, 'isDefault' => true]);
        foreach ($addresses as $address) {
            if ($exceptId !== null && $address->getId() === $exceptId) {
                continue;
            }
            $address->setIsDefault(false);
        }
    }

    private function addressToArray(CustomerAddress $address): array
    {
        return [
            'id' => $address->getId(),
            'userId' => $address->getUser()?->getId(),
            'label' => $address->getLabel(),
            'recipientName' => $address->getRecipientName(),
            'phone' => $address->getPhone(),
            'line1' => $address->getLine1(),
            'line2' => $address->getLine2(),
            'city' => $address->getCity(),
            'postalCode' => $address->getPostalCode(),
            'country' => $address->getCountry(),
            'isDefault' => $address->isDefault(),
        ];
    }

    private function buildQuote(array $lines, ProductRepository $productRepository, ProductVariantRepository $variantRepository): array
    {
        $normalized = [];
        $subtotal = 0;

        foreach ($lines as $line) {
            $productId = $line['productId'] ?? null;
            $quantity = $line['quantity'] ?? null;
            $variantId = $line['variantId'] ?? null;

            if (!is_numeric((string) $productId) || !is_numeric((string) $quantity)) {
                return ['error' => 'Chaque ligne doit contenir un productId numerique et une quantite', 'data' => null];
            }

            $qty = max(1, (int) $quantity);
            $product = $productRepository->find((int) $productId);
            if ($product === null || !$product->isActive()) {
                return ['error' => sprintf('Produit %s non disponible', $productId), 'data' => null];
            }

            $unitPrice = $product->getSalePrice() ?? $product->getPrice() ?? 0;
            $variant = null;

            if ($variantId !== null && $variantId !== '') {
                if (!is_numeric((string) $variantId)) {
                    return ['error' => 'variantId doit etre numerique', 'data' => null];
                }

                $variant = $variantRepository->find((int) $variantId);
                if ($variant === null || $variant->getProduct()?->getId() !== $product->getId()) {
                    return ['error' => sprintf('La variante %s n est pas disponible pour le produit %s', $variantId, $productId), 'data' => null];
                }

                $unitPrice = $variant->getPrice() ?? $unitPrice;
                $available = (int) ($variant->getQuantity() ?? 0);
                if ($qty > $available) {
                    return ['error' => sprintf('La quantite demandee depasse le stock pour la variante %s', $variantId), 'data' => null];
                }
            } else {
                $available = (int) ($product->getQuantity() ?? 0);
                if ($qty > $available) {
                    return ['error' => sprintf('La quantite demandee depasse le stock pour le produit %s', $productId), 'data' => null];
                }
            }

            $lineTotal = $unitPrice * $qty;
            $subtotal += $lineTotal;

            $normalized[] = [
                'productId' => (int) $productId,
                'variantId' => $variant?->getId(),
                'quantity' => $qty,
                'unitPrice' => $unitPrice,
                'lineTotal' => $lineTotal,
                'product' => ProductDataMapper::toArray($product),
            ];
        }

        $shippingCost = $subtotal >= 90 || $subtotal === 0 ? 0 : 5;
        $total = $subtotal + $shippingCost;

        return ['error' => null, 'data' => [
            'lines' => $normalized,
            'subtotal' => $subtotal,
            'shippingCost' => $shippingCost,
            'total' => $total,
        ]];
    }

    private function generateOrderNumber(): string
    {
        return 'VSB-'.(new \DateTimeImmutable())->format('YmdHis').'-'.strtoupper(bin2hex(random_bytes(3)));
    }

    private function orderToArray(StoreOrder $order): array
    {
        return [
            'id' => $order->getId(),
            'orderNumber' => $order->getOrderNumber(),
            'userId' => $order->getUser()?->getId(),
            'status' => $order->getStatus(),
            'subtotal' => $order->getSubtotal(),
            'shippingCost' => $order->getShippingCost(),
            'total' => $order->getTotal(),
            'shippingSnapshot' => $order->getShippingSnapshot(),
            'createdAt' => $order->getCreatedAt()?->format(DATE_ATOM),
            'items' => array_map(static fn (StoreOrderItem $item) => [
                'id' => $item->getId(),
                'productId' => $item->getProduct()?->getId(),
                'variantId' => $item->getVariant()?->getId(),
                'quantity' => $item->getQuantity(),
                'unitPrice' => $item->getUnitPrice(),
                'totalPrice' => $item->getTotalPrice(),
                'product' => $item->getProduct() ? ProductDataMapper::toArray($item->getProduct()) : null,
            ], $order->getItems()->toArray()),
        ];
    }
}
