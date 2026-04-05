<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Compte inspecteur : accès équivalent visiteur pour la gestion, connexion pour signalements fiables.
 *
 * Identifiants par défaut : inspector / InspectorFixture!2026
 */
final class InspectorUserFixtures extends Fixture
{
    public const string INSPECTOR_USERNAME = 'inspector';

    /** @internal */
    public const string INSPECTOR_PLAIN_PASSWORD = 'InspectorFixture!2026';

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $repo = $manager->getRepository(User::class);
        if (null !== $repo->findOneBy(['username' => self::INSPECTOR_USERNAME])) {
            return;
        }

        $user = new User();
        $user->setUsername(self::INSPECTOR_USERNAME);
        $user->setRoles(['ROLE_INSPECTOR']);
        $user->setPassword($this->passwordHasher->hashPassword($user, self::INSPECTOR_PLAIN_PASSWORD));

        $manager->persist($user);
        $manager->flush();
    }
}
