<?php

namespace App\Controller;

use App\Entity\User;
use App\Security\LoginFormAuthenticator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;

final class RegisterController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
        UserAuthenticatorInterface $userAuthenticator,
        LoginFormAuthenticator $authenticator,
    ): Response {
        $user = new User();
        $error = null;

        if ($request->isMethod('POST')) {
            $user->setUsername($request->request->get('username', ''));
            $plainPassword = $request->request->get('password', '');
            if ($plainPassword) {
                $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
                $user->setPassword($hashedPassword);
            }

            if (!$user->getUsername() || !$plainPassword) {
                $error = 'Veuillez remplir tous les champs obligatoires.';
            } else {
                $user->setRoles(['ROLE_USER']);
                $em->persist($user);
                $em->flush();

                return $userAuthenticator->authenticateUser(
                    $user,
                    $authenticator,
                    $request
                );
            }
        } else {
            // Initialisation des propriétés pour éviter l'erreur Twig avec les propriétés typées
            $user->setUsername('');
            $user->setPassword('');
        }

        return $this->render('register/index.html.twig', [
            'user' => $user,
            'error' => $error,
        ]);
    }

    #[Route('/register/success', name: 'app_register_success')]
    public function success(): Response
    {
        return new Response('Inscription réussie !');
    }
}
