<?php

namespace App\Controller;

use App\Entity\ApiKey;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

class ApiKeyController extends AbstractController
{
    /**
     * @Route("/create-api-key", name="create_api_key", methods={"POST"})
     */
    public function createApiKey(Request $request,
                               EntityManagerInterface $entityManager,
                               UserPasswordHasherInterface $passwordHasher): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Validate the data (consider using Symfony's Validator for complex validation)
        if (!isset($data['clientId'])) {
            return new JsonResponse(['error' => 'Missing required fields'], Response::HTTP_BAD_REQUEST);
        }
        $user = new User();
        $user->setUserId($data['clientId']);
        $user->setName($data['clientName']);
        // Générer un mot de passe aléatoire sécurisé
        $plainPassword = bin2hex(random_bytes(8)); // 8 bytes = 16 caractères hexadécimaux
        $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);

        $user->setPassword($hashedPassword);

        // Définissez les propriétés de l'utilisateur comme nécessaire
        $entityManager->persist($user);

        // Create and persist the ApiKey entity
        $apiKey = new ApiKey();
        $apiKey->setToken(bin2hex(random_bytes(16))); // Generate a secure API key
        $apiKey->setClientId($data['clientId']);
        $apiKey->setIsActive(true); // Set default values as needed
        $apiKey->setUser($user);

        $entityManager->persist($apiKey);
        $entityManager->flush();

        // Return a success response
        return new JsonResponse([
            'success' => 'User and API Key created successfully',
            'userId' => $user->getId(),
            'apiKey' => $apiKey->getToken()
        ], Response::HTTP_CREATED);
    }
}
