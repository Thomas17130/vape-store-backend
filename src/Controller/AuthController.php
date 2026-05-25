<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\JwtTokenManager;
use App\Security\RequestAuth;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/auth')]
class AuthController extends AbstractController
{
    public function __construct(
        private readonly RequestAuth $requestAuth,
        private readonly JwtTokenManager $jwtTokenManager,
    ) {
    }

    #[Route('/signup', name: 'api_auth_signup', methods: ['POST'])]
    public function signup(Request $request, UserRepository $userRepository, EntityManagerInterface $entityManager): JsonResponse
    {
        $payload = $this->readPayload($request);
        if ($payload === null) {
            return new JsonResponse(['error' => 'Corps JSON invalide'], Response::HTTP_BAD_REQUEST);
        }

        $name = isset($payload['name']) ? trim((string) $payload['name']) : '';
        $email = isset($payload['email']) ? trim((string) $payload['email']) : '';
        $address = isset($payload['address']) ? trim((string) $payload['address']) : '';
        $password = isset($payload['password']) ? (string) $payload['password'] : '';

        if ($name === '' || $email === '' || $address === '' || $password === '') {
            return new JsonResponse(['error' => 'nom, e-mail, adresse et mot de passe sont requis'], Response::HTTP_BAD_REQUEST);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return new JsonResponse(['error' => 'Format d e-mail invalide'], Response::HTTP_BAD_REQUEST);
        }

        if (mb_strlen($password) < 8) {
            return new JsonResponse(['error' => 'Le mot de passe doit contenir au moins 8 caracteres'], Response::HTTP_BAD_REQUEST);
        }

        if ($userRepository->findOneBy(['email' => $email]) !== null) {
            return new JsonResponse(['error' => 'Cet e-mail existe deja'], Response::HTTP_CONFLICT);
        }

        $user = new User();
        $user->setName($name);
        $user->setEmail($email);
        $user->setAddress($address);
        $user->setPasswordHash(password_hash($password, PASSWORD_ARGON2ID));
        $user->setRole($userRepository->hasAtLeastOneAdmin() ? User::ROLE_USER : User::ROLE_ADMIN);
        $refreshToken = $this->jwtTokenManager->createRefreshToken($user);

        $entityManager->persist($user);
        $entityManager->flush();

        return new JsonResponse($this->authResponse(
            message: 'Inscription reussie',
            user: $user,
            refreshToken: $refreshToken,
        ), Response::HTTP_CREATED);
    }

    #[Route('/login', name: 'api_auth_login', methods: ['POST'])]
    public function login(Request $request, UserRepository $userRepository, EntityManagerInterface $entityManager): JsonResponse
    {
        $payload = $this->readPayload($request);
        if ($payload === null) {
            return new JsonResponse(['error' => 'Corps JSON invalide'], Response::HTTP_BAD_REQUEST);
        }

        $email = isset($payload['email']) ? trim((string) $payload['email']) : '';
        $password = isset($payload['password']) ? (string) $payload['password'] : '';
        if ($email === '' || $password === '') {
            return new JsonResponse(['error' => 'e-mail et mot de passe sont requis'], Response::HTTP_BAD_REQUEST);
        }

        $user = $userRepository->findOneBy(['email' => $email]);
        if ($user === null || !password_verify($password, (string) $user->getPasswordHash())) {
            return new JsonResponse(['error' => 'Identifiants invalides'], Response::HTTP_UNAUTHORIZED);
        }

        $refreshToken = $this->jwtTokenManager->createRefreshToken($user);
        $entityManager->persist($user);
        $entityManager->flush();

        return new JsonResponse($this->authResponse(
            message: 'Connexion reussie',
            user: $user,
            refreshToken: $refreshToken,
        ));
    }

    #[Route('/refresh', name: 'api_auth_refresh', methods: ['POST'])]
    public function refresh(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $payload = $this->readPayload($request);
        if ($payload === null) {
            return new JsonResponse(['error' => 'Corps JSON invalide'], Response::HTTP_BAD_REQUEST);
        }

        $refreshToken = isset($payload['refreshToken']) ? trim((string) $payload['refreshToken']) : '';
        $user = $this->jwtTokenManager->resolveUserFromRefreshToken($refreshToken);
        if ($user === null) {
            return new JsonResponse(['error' => 'Refresh token invalide ou expire'], Response::HTTP_UNAUTHORIZED);
        }

        $newRefreshToken = $this->jwtTokenManager->createRefreshToken($user);
        $entityManager->flush();

        return new JsonResponse($this->authResponse(
            message: 'Session renouvelee',
            user: $user,
            refreshToken: $newRefreshToken,
        ));
    }

    #[Route('/logout', name: 'api_auth_logout', methods: ['POST'])]
    public function logout(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->requestAuth->resolveUser($request);
        if ($user === null) {
            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        }

        $this->jwtTokenManager->invalidateRefreshToken($user);
        $entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/me', name: 'api_auth_me', methods: ['GET'])]
    public function me(Request $request): JsonResponse
    {
        $user = $this->requestAuth->resolveUser($request);
        if ($user === null) {
            return new JsonResponse(['error' => 'Authentification requise'], Response::HTTP_UNAUTHORIZED);
        }

        return new JsonResponse(['user' => $this->toUserArray($user)]);
    }

    #[Route('/me', name: 'api_auth_me_update', methods: ['PATCH'])]
    public function updateMe(Request $request, UserRepository $userRepository, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->requestAuth->resolveUser($request);
        if ($user === null) {
            return new JsonResponse(['error' => 'Authentification requise'], Response::HTTP_UNAUTHORIZED);
        }

        $payload = $this->readPayload($request);
        if ($payload === null) {
            return new JsonResponse(['error' => 'Corps JSON invalide'], Response::HTTP_BAD_REQUEST);
        }

        if (array_key_exists('name', $payload)) {
            $name = trim((string) $payload['name']);
            if ($name === '') {
                return new JsonResponse(['error' => 'name ne peut pas etre vide'], Response::HTTP_BAD_REQUEST);
            }
            $user->setName($name);
        }

        if (array_key_exists('address', $payload)) {
            $address = trim((string) $payload['address']);
            if ($address === '') {
                return new JsonResponse(['error' => 'address ne peut pas etre vide'], Response::HTTP_BAD_REQUEST);
            }
            $user->setAddress($address);
        }

        if (array_key_exists('email', $payload)) {
            $email = trim((string) $payload['email']);
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return new JsonResponse(['error' => 'Format d e-mail invalide'], Response::HTTP_BAD_REQUEST);
            }

            $existing = $userRepository->findOneBy(['email' => $email]);
            if ($existing !== null && $existing->getId() !== $user->getId()) {
                return new JsonResponse(['error' => 'Cet e-mail existe deja'], Response::HTTP_CONFLICT);
            }

            $user->setEmail($email);
        }

        if (array_key_exists('password', $payload)) {
            $password = (string) $payload['password'];
            if (mb_strlen($password) < 8) {
                return new JsonResponse(['error' => 'Le mot de passe doit contenir au moins 8 caracteres'], Response::HTTP_BAD_REQUEST);
            }
            $user->setPasswordHash(password_hash($password, PASSWORD_ARGON2ID));
        }

        $entityManager->flush();

        return new JsonResponse([
            'message' => 'Profil mis a jour',
            'user' => $this->toUserArray($user),
        ]);
    }

    #[Route('/me', name: 'api_auth_me_delete', methods: ['DELETE'])]
    public function deleteMe(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->requestAuth->resolveUser($request);
        if ($user === null) {
            return new JsonResponse(['error' => 'Authentification requise'], Response::HTTP_UNAUTHORIZED);
        }

        foreach ($user->getOrders() as $order) {
            $order->setUser(null);
        }

        foreach ($user->getCartLines() as $cartLine) {
            $cartLine->setUser(null);
        }

        $entityManager->remove($user);
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

    private function toUserArray(User $user): array
    {
        return [
            'id' => $user->getId(),
            'name' => $user->getName(),
            'email' => $user->getEmail(),
            'address' => $user->getAddress(),
            'role' => $user->getRole(),
        ];
    }

    private function authResponse(string $message, User $user, string $refreshToken): array
    {
        return [
            'message' => $message,
            'token' => $this->jwtTokenManager->createAccessToken($user),
            'expiresIn' => $this->jwtTokenManager->getAccessTokenTtl(),
            'refreshToken' => $refreshToken,
            'refreshExpiresAt' => $user->getRefreshTokenExpiresAt()?->format(\DateTimeInterface::ATOM),
            'user' => $this->toUserArray($user),
        ];
    }
}
