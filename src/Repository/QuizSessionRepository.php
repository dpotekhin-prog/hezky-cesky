<?php

namespace App\Repository;

use App\Entity\QuizSession;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<QuizSession> */
class QuizSessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, QuizSession::class);
    }

    /** @return list<QuizSession> */
    public function findRecent(User $user, int $limit = 10): array
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.topic', 't')->addSelect('t')
            ->where('s.user = :user')
            ->setParameter('user', $user)
            ->orderBy('s.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
