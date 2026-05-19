<?php

namespace App\Controller;

use App\Entity\Brand;
use App\Repository\BrandRepository;
use App\Security\RequestAuth;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/brands')]
class BrandController extends AbstractController
{
    public function __construct(private readonly RequestAuth $requestAuth)
    {
    }

    #[Route('', name: 'api_brands_list', methods: ['GET'])]
    public function list(BrandRepository $brandRepository): JsonResponse
    {
        $brands = $brandRepository->findBy([], ['name' => 'ASC']);
        $data = array_map(
            fn (Brand $brand) => [
                'id' => $brand->getId(),
                'name' => $brand->getName(),
            ],
            $brands
        );

        return new JsonResponse($data);
    }

    #[Route('', name: 'api_brands_create', methods: ['POST'])]
    public function create(Request $request, BrandRepository $brandRepository, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->requestAuth->resolveUser($request);
        if (!$this->requestAuth->isAdmin($user)) {
            return new JsonResponse(['error' => 'Acces reserve aux administrateurs'], Response::HTTP_FORBIDDEN);
        }

        $payload = $this->readPayload($request);
        if ($payload === null) {
            return new JsonResponse(['error' => 'Corps JSON invalide'], Response::HTTP_BAD_REQUEST);
        }

        $name = isset($payload['name']) ? trim((string) $payload['name']) : '';
        if ($name === '') {
            return new JsonResponse(['error' => 'name est requis'], Response::HTTP_BAD_REQUEST);
        }

        $existing = $brandRepository->findOneBy(['name' => $name]);
        if ($existing !== null) {
            return new JsonResponse([
                'id' => $existing->getId(),
                'name' => $existing->getName(),
            ]);
        }

        $brand = new Brand();
        $brand->setName($name);
        $entityManager->persist($brand);
        $entityManager->flush();

        return new JsonResponse([
            'id' => $brand->getId(),
            'name' => $brand->getName(),
        ], Response::HTTP_CREATED);
    }

    private function readPayload(Request $request): ?array
    {
        try {
            return $request->toArray();
        } catch (\JsonException) {
            return null;
        }
    }
}
