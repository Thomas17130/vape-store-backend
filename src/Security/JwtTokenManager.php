<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class JwtTokenManager
{
    public const ACCESS_TOKEN_TTL = 900;
    public const REFRESH_TOKEN_TTL = 2592000;

    private const ISSUER = 'vape-store-api';
    private const AUDIENCE = 'vape-store-frontend';
    private const ALGORITHM = 'HS256';

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly ParameterBagInterface $parameterBag,
    ) {
    }

    public function createAccessToken(User $user): string
    {
        $now = time();

        return JWT::encode([
            'iss' => self::ISSUER,
            'aud' => self::AUDIENCE,
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + self::ACCESS_TOKEN_TTL,
            'sub' => (string) $user->getId(),
            'email' => $user->getEmail(),
            'role' => $user->getRole(),
            'typ' => 'access',
        ], $this->getSecret(), self::ALGORITHM);
    }

    public function createRefreshToken(User $user): string
    {
        $refreshToken = bin2hex(random_bytes(64));
        $user->setRefreshTokenHash($this->hashRefreshToken($refreshToken));
        $user->setRefreshTokenExpiresAt(new \DateTimeImmutable(sprintf('+%d seconds', self::REFRESH_TOKEN_TTL)));

        return $refreshToken;
    }

    public function resolveUserFromAccessToken(string $token): ?User
    {
        try {
            $payload = (array) JWT::decode($token, new Key($this->getSecret(), self::ALGORITHM));
        } catch (\Throwable) {
            return null;
        }

        if (($payload['typ'] ?? null) !== 'access') {
            return null;
        }

        $userId = (int) ($payload['sub'] ?? 0);
        if ($userId <= 0) {
            return null;
        }

        return $this->userRepository->find($userId);
    }

    public function resolveUserFromRefreshToken(string $refreshToken): ?User
    {
        if ($refreshToken === '') {
            return null;
        }

        $user = $this->userRepository->findOneBy(['refreshTokenHash' => $this->hashRefreshToken($refreshToken)]);
        if ($user === null) {
            return null;
        }

        $expiresAt = $user->getRefreshTokenExpiresAt();
        if ($expiresAt === null || $expiresAt <= new \DateTimeImmutable()) {
            return null;
        }

        return $user;
    }

    public function invalidateRefreshToken(User $user): void
    {
        $user->setRefreshTokenHash(null);
        $user->setRefreshTokenExpiresAt(null);
    }

    public function getAccessTokenTtl(): int
    {
        return self::ACCESS_TOKEN_TTL;
    }

    public function getRefreshTokenTtl(): int
    {
        return self::REFRESH_TOKEN_TTL;
    }

    private function hashRefreshToken(string $refreshToken): string
    {
        return hash('sha256', $refreshToken);
    }

    private function getSecret(): string
    {
        $secret = trim((string) ($_ENV['JWT_SECRET'] ?? $_SERVER['JWT_SECRET'] ?? ''));
        if ($secret !== '') {
            return $secret;
        }

        $appSecret = trim((string) $this->parameterBag->get('kernel.secret'));
        if ($appSecret !== '') {
            return $appSecret;
        }

        throw new \RuntimeException('JWT_SECRET ou APP_SECRET doit etre configure.');
    }
}
