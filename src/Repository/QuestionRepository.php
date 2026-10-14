<?php

namespace App\Repository;

use App\Entity\Question;
use App\Entity\Topic;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Question> */
class QuestionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Question::class);
    }

    /** @return list<int> */
    public function findActiveIds(?int $level, ?Topic $topic): array
    {
        $qb = $this->createQueryBuilder('q')
            ->select('q.id')
            ->where('q.active = true');
        if (null !== $level) {
            $qb->andWhere('q.level = :level')->setParameter('level', $level);
        }
        if (null !== $topic) {
            $qb->andWhere('q.topic = :topic')->setParameter('topic', $topic);
        }

        return array_map('intval', $qb->getQuery()->getSingleColumnResult());
    }

    /**
     * Lightweight list of active questions for progress counting.
     *
     * @return list<array{id: int, level: int, topic: string}>
     */
    public function findActiveMeta(): array
    {
        $rows = $this->createQueryBuilder('q')
            ->select('q.id, q.level, t.code AS topic')
            ->join('q.topic', 't')
            ->where('q.active = true')
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $r) => ['id' => (int) $r['id'], 'level' => (int) $r['level'], 'topic' => $r['topic']], $rows);
    }
}
