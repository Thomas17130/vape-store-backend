<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Request;

class RequestAuth
{
    public function __construct(private readonly UserRepository $userRepository)
    {
    }

    public function resolveUser(Request $request): ?User
    {
        $token = $this->extractBearerToken($request);
        if ($token === null) {
            return null;
        }

        return $this->userRepository->findOneBy(['authToken' => $token]);
    }

    public function isAdmin(?User $user): bool
    {
        return $user?->isAdmin() === true;
    }

    private function extractBearerToken(Request $request): ?string
    {
        $header = trim((string) $request->headers->get('Authorization', ''));
        if ($header === '') {
            return null;
        }

        if (!preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return null;
        }

        $token = trim((string) ($matches[1] ?? ''));

        return $token === '' ? null : $token;
    }
}
