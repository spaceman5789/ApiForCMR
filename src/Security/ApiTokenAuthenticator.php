<?php

namespace App\Security;

use App\Entity\ApiKey;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use App\Entity\User;

class ApiTokenAuthenticator extends AbstractAuthenticator
{
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function supports(Request $request): ?bool
    {
        return $request->headers->has('X-Api-Key');
    }

    public function authenticate(Request $request): Passport
    {
        $apiKeyValue = $request->headers->get('X-Api-Key');
        if (!$apiKeyValue) {
            throw new CustomUserMessageAuthenticationException('No API key provided');
        }

        $apiKey = $this->entityManager->getRepository(ApiKey::class)->findOneBy(['token' => $apiKeyValue, 'isActive' => true]);
        if (!$apiKey) {
            throw new CustomUserMessageAuthenticationException('Invalid API Token');
        }
        return new SelfValidatingPassport(new UserBadge($apiKey->getClientId()));

    }

    public function onAuthenticationSuccess(Request $request, \Symfony\Component\Security\Core\Authentication\Token\TokenInterface $token, string $firewallName): ?Response
    {
        // Laissez la requête continuer
        return null;
    }

    public function onAuthenticationFailure(Request $request, \Symfony\Component\Security\Core\Exception\AuthenticationException $exception): ?Response
    {
        $data = ['message' => 'Authentication Failed: ' . $exception->getMessageKey()];
        return new JsonResponse($data, Response::HTTP_UNAUTHORIZED);
    }

    public function supportsRememberMe(): bool
    {
        return true;
    }
}
