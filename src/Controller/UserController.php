<?php

// src/Controller/UserController.php

namespace App\Controller;

use App\Entity\User;
use App\Entity\ApiKey;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserController extends AbstractController
{
    #[Route('/api/user/create', name: 'api_user_create', methods: ['POST'])]
    public function createUser(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher): Response
    {
        // Vérifiez ici si la clé API est valide et autorisée à créer des utilisateurs

        $data = json_decode($request->getContent(), true);

        $user = new User();
        $user->setUserId($data['username']);
        // Encodez et définissez le mot de passe ici si nécessaire
        // $user->setPassword($passwordHasher->hashPassword($user, $data['password']));

        $entityManager->persist($user);
        $entityManager->flush();

        return $this->json(['message' => 'User created successfully', 'userId' => $user->getId()]);
    }
}
