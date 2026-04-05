<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Compte « manager » pour clôturer les signalements (ROLE_MANAGER).
 *
 * Connexion par défaut : manager / ManagerFixture!2026
 */
final class ManagerUserFixtures extends Fixture
{
    public const string MANAGER_USERNAME = 'manager';

    public const string MANAGER_PLAIN_PASSWORD = 'ManagerFixture!2026';

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $repo = $manager->getRepository(User::class);
        if (null !== $repo->findOneBy(['username' => self::MANAGER_USERNAME])) {
            return;
        }

        $user = new User();
        $user->setUsername(self::MANAGER_USERNAME);
        $user->setRoles(['ROLE_MANAGER', 'ROLE_USER']);
        $user->setPassword($this->passwordHasher->hashPassword($user, self::MANAGER_PLAIN_PASSWORD));

        $manager->persist($user);
        $manager->flush();
    }
}
