<?php

namespace App\Controller;

use App\Entity\Brand;
use App\Entity\Product;
use App\Entity\StoreOrder;
use App\Entity\User;
use App\Security\RequestAuth;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin')]
class AdminController extends AbstractController
{
    public function __construct(private readonly RequestAuth $requestAuth)
    {
    }

    #[Route('/stats', name: 'api_admin_stats', methods: ['GET'])]
    public function stats(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->requestAuth->resolveUser($request);
        if (!$this->requestAuth->isAdmin($user)) {
            return new JsonResponse(['error' => 'Acces reserve aux administrateurs'], Response::HTTP_FORBIDDEN);
        }

        $productCount = (int) $entityManager->getRepository(Product::class)->count([]);
        $brandCount = (int) $entityManager->getRepository(Brand::class)->count([]);
        $userCount = (int) $entityManager->getRepository(User::class)->count([]);
        $orderCount = (int) $entityManager->getRepository(StoreOrder::class)->count([]);

        $inventory = $entityManager->createQueryBuilder()
            ->select('COALESCE(SUM(p.quantity), 0) AS quantitySum', 'COALESCE(SUM(p.price * p.quantity), 0) AS valueSum')
            ->from(Product::class, 'p')
            ->getQuery()
            ->getSingleResult();

        return new JsonResponse([
            'products' => $productCount,
            'brands' => $brandCount,
            'users' => $userCount,
            'orders' => $orderCount,
            'inventoryUnits' => (int) ($inventory['quantitySum'] ?? 0),
            'inventoryValue' => (int) ($inventory['valueSum'] ?? 0),
        ]);
    }
}
