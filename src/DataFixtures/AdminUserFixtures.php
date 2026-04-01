<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Loads a manager admin account for local/dev use.
 *
 * Default login: admin / AdminFixture!2026
 * Change the password after first login in production.
 */
final class AdminUserFixtures extends Fixture
{
    public const string ADMIN_USERNAME = 'admin';

    /** @internal Exposed for documentation; override via env or manual edit after load if needed */
    public const string ADMIN_PLAIN_PASSWORD = 'AdminFixture!2026';

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $repo = $manager->getRepository(User::class);
        if (null !== $repo->findOneBy(['username' => self::ADMIN_USERNAME])) {
            return;
        }

        $user = new User();
        $user->setUsername(self::ADMIN_USERNAME);
        $user->setRoles(['ROLE_ADMIN', 'ROLE_USER']);
        $user->setPassword($this->passwordHasher->hashPassword($user, self::ADMIN_PLAIN_PASSWORD));

        $manager->persist($user);
        $manager->flush();
    }
}
