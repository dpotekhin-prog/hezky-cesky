<?php

namespace App\Repository;

use App\Entity\Answer;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Answer> */
class AnswerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Answer::class);
    }

    /** @return array{total: int, correct: int} */
    public function totals(User $user): array
    {
        $row = $this->createQueryBuilder('a')
            ->select('COUNT(a.id) AS total, SUM(CASE WHEN a.correct = true THEN 1 ELSE 0 END) AS correct')
            ->where('a.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleResult();

        return ['total' => (int) $row['total'], 'correct' => (int) $row['correct']];
    }

    /** @return array<int, array{total: int, correct: int}> keyed by level */
    public function totalsByLevel(User $user): array
    {
        $rows = $this->createQueryBuilder('a')
            ->select('q.level AS level, COUNT(a.id) AS total, SUM(CASE WHEN a.correct = true THEN 1 ELSE 0 END) AS correct')
            ->join('a.question', 'q')
            ->where('a.user = :user')
            ->setParameter('user', $user)
            ->groupBy('q.level')
            ->getQuery()
            ->getArrayResult();

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row['level']] = ['total' => (int) $row['total'], 'correct' => (int) $row['correct']];
        }

        return $result;
    }

    /** @return array<int, array{total: int, correct: int}> keyed by topic id */
    public function totalsByTopic(User $user): array
    {
        $rows = $this->createQueryBuilder('a')
            ->select('IDENTITY(q.topic) AS topic, COUNT(a.id) AS total, SUM(CASE WHEN a.correct = true THEN 1 ELSE 0 END) AS correct')
            ->join('a.question', 'q')
            ->where('a.user = :user')
            ->setParameter('user', $user)
            ->groupBy('q.topic')
            ->getQuery()
            ->getArrayResult();

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row['topic']] = ['total' => (int) $row['total'], 'correct' => (int) $row['correct']];
        }

        return $result;
    }

    /**
     * Ids of all questions the user has ever answered (right or wrong).
     *
     * @return array<int, true> keyed by question id
     */
    public function answeredQuestionIds(User $user): array
    {
        $ids = $this->createQueryBuilder('a')
            ->select('DISTINCT IDENTITY(a.question)')
            ->where('a.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleColumnResult();

        return array_fill_keys(array_map('intval', $ids), true);
    }

    /**
     * Latest answers where the most recent attempt at the question was wrong.
     *
     * @return list<Answer>
     */
    public function findOpenMistakes(User $user): array
    {
        return $this->createQueryBuilder('a')
            ->join('a.question', 'q')->addSelect('q')
            ->join('q.topic', 't')->addSelect('t')
            ->where('a.user = :user')
            ->andWhere('a.correct = false')
            ->andWhere('q.active = true')
            ->andWhere('a.id = (SELECT MAX(a2.id) FROM '.Answer::class.' a2 WHERE a2.user = :user AND a2.question = a.question)')
            ->setParameter('user', $user)
            ->orderBy('a.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<Answer> */
    public function findRecent(User $user, int $limit = 50): array
    {
        return $this->createQueryBuilder('a')
            ->join('a.question', 'q')->addSelect('q')
            ->join('q.topic', 't')->addSelect('t')
            ->where('a.user = :user')
            ->setParameter('user', $user)
            ->orderBy('a.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findLastAnswerInSession(int $sessionId): ?Answer
    {
        return $this->createQueryBuilder('a')
            ->join('a.question', 'q')->addSelect('q')
            ->where('IDENTITY(a.session) = :session')
            ->setParameter('session', $sessionId)
            ->orderBy('a.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
