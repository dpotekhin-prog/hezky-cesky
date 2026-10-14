<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<User> */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /** @return list<User> */
    public function findChildren(): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.roles NOT LIKE :parent')
            ->setParameter('parent', '%'.User::ROLE_PARENT.'%')
            ->orderBy('u.username')
            ->getQuery()
            ->getResult();
    }
}
