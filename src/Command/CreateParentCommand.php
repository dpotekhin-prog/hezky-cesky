<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:user:parent', description: 'Create a parent account, or promote/reset an existing user to parent')]
final class CreateParentCommand
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function __invoke(SymfonyStyle $io, #[Argument('Login name')] string $username): int
    {
        $password = $io->askHidden('Password (min 6 chars)', static function (?string $value): string {
            if (null === $value || mb_strlen($value) < 6) {
                throw new \RuntimeException('Password must have at least 6 characters.');
            }

            return $value;
        });

        $user = $this->users->findOneBy(['username' => $username]) ?? (new User())->setUsername($username);
        $user->setRoles([User::ROLE_PARENT]);
        $user->setPassword($this->hasher->hashPassword($user, $password));
        $this->em->persist($user);
        $this->em->flush();

        $io->success(sprintf('Parent account "%s" is ready.', $username));

        return Command::SUCCESS;
    }
}
