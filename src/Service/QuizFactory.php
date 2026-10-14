<?php

namespace App\Service;

use App\Entity\QuizSession;
use App\Entity\Topic;
use App\Entity\User;
use App\Repository\AnswerRepository;
use App\Repository\QuestionRepository;
use Doctrine\ORM\EntityManagerInterface;

final class QuizFactory
{
    public const QUESTIONS_PER_QUIZ = 10;

    public function __construct(
        private readonly QuestionRepository $questions,
        private readonly AnswerRepository $answers,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function create(User $user, ?int $level, ?Topic $topic): ?QuizSession
    {
        $ids = $this->questions->findActiveIds($level, $topic);
        if ([] === $ids) {
            return null;
        }

        // Every question is asked once: anything the child has already answered is skipped.
        // Wrong answers come back only through the "fix mistakes" mode.
        $answered = $this->answers->answeredQuestionIds($user);
        $ids = array_values(array_filter($ids, static fn (int $id) => !isset($answered[$id])));
        if ([] === $ids) {
            return null;
        }
        shuffle($ids);
        $picked = array_slice($ids, 0, self::QUESTIONS_PER_QUIZ);

        return $this->persist(new QuizSession($user, $level, $topic, QuizSession::MODE_NORMAL, $picked));
    }

    public function createFromMistakes(User $user): ?QuizSession
    {
        $ids = [];
        foreach ($this->answers->findOpenMistakes($user) as $answer) {
            $ids[] = (int) $answer->getQuestion()->getId();
        }
        if ([] === $ids) {
            return null;
        }
        shuffle($ids);

        return $this->persist(new QuizSession($user, null, null, QuizSession::MODE_MISTAKES, array_slice($ids, 0, self::QUESTIONS_PER_QUIZ)));
    }

    private function persist(QuizSession $session): QuizSession
    {
        $this->em->persist($session);
        $this->em->flush();

        return $session;
    }
}
