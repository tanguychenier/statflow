<?php

declare(strict_types=1);

/*
 * This file is part of Statflow.
 *
 * (c) Tanguy Chénier <tanguychenier@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Integration\Identity\Infrastructure\Persistence;

use App\Identity\Domain\Model\User;
use App\Identity\Domain\Port\UserRepository;
use App\Identity\Domain\ValueObject\EmailAddress;
use App\Identity\Domain\ValueObject\HashedPassword;
use App\Shared\Domain\ValueObject\Uuid;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration test for the Doctrine user adapter against a real PostgreSQL
 * database. Runs in the dedicated container phase; the schema must be migrated
 * first. Each test runs inside a transaction that is rolled back so the suite is
 * order-independent.
 */
#[CoversNothing]
final class DoctrineUserRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    private UserRepository $users;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->users = self::getContainer()->get(UserRepository::class);
        $this->em->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    #[Test]
    public function itPersistsAndReloadsAUser(): void
    {
        $id = Uuid::generate();
        $this->users->save(User::register(
            $id,
            EmailAddress::fromString('alice@example.com'),
            'Alice',
            HashedPassword::fromHash('$2y$10$hash'),
            new \DateTimeImmutable(),
        ));
        $this->em->clear();

        $found = $this->users->findById($id);

        self::assertNotNull($found);
        self::assertSame('alice@example.com', $found->email()->getValue());
        self::assertFalse($found->isEmailVerified());
    }

    #[Test]
    public function lookupByEmailIsCaseInsensitive(): void
    {
        $this->users->save(User::register(
            Uuid::generate(),
            EmailAddress::fromString('alice@example.com'),
            'Alice',
            HashedPassword::fromHash('$2y$10$hash'),
            new \DateTimeImmutable(),
        ));
        $this->em->clear();

        self::assertNotNull($this->users->findByEmail(EmailAddress::fromString('ALICE@example.com')));
        self::assertTrue($this->users->existsByEmail(EmailAddress::fromString('alice@EXAMPLE.com')));
    }

    #[Test]
    public function softDeletedUsersAreInvisibleAndTheEmailCanBeReused(): void
    {
        $first = User::register(
            Uuid::generate(),
            EmailAddress::fromString('reuse@example.com'),
            'First',
            HashedPassword::fromHash('$2y$10$hash'),
            new \DateTimeImmutable(),
        );
        $this->users->save($first);
        $first->softDelete(new \DateTimeImmutable());
        $this->users->save($first);
        $this->em->clear();

        self::assertFalse($this->users->existsByEmail(EmailAddress::fromString('reuse@example.com')));

        $this->users->save(User::register(
            Uuid::generate(),
            EmailAddress::fromString('reuse@example.com'),
            'Second',
            HashedPassword::fromHash('$2y$10$hash'),
            new \DateTimeImmutable(),
        ));
        $this->em->clear();

        self::assertNotNull($this->users->findByEmail(EmailAddress::fromString('reuse@example.com')));
    }
}
