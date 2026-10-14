<?php

namespace App\Controller;

use App\Entity\Answer;
use App\Entity\Question;
use App\Entity\QuizSession;
use App\Entity\User;
use App\Repository\AnswerRepository;
use App\Repository\QuestionRepository;
use App\Repository\TopicRepository;
use App\Service\AnswerChecker;
use App\Service\PlayerRoster;
use App\Service\QuizFactory;
use App\Service\StatsProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/{_locale}/quiz', requirements: ['_locale' => 'cs|en'])]
final class QuizController extends AbstractController
{
    public function __construct(
        private readonly QuestionRepository $questions,
        private readonly AnswerRepository $answers,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/start', name: 'quiz_start', methods: ['POST'])]
    public function start(Request $request, #[CurrentUser] User $user, QuizFactory $factory, TopicRepository $topics, StatsProvider $stats): Response
    {
        $this->assertCsrf($request, 'quiz_start');

        $level = $request->request->getInt('level') ?: null;
        if (null !== $level && !in_array($level, Question::LEVELS, true)) {
            throw $this->createNotFoundException();
        }
        if (null !== $level && !$stats->isLevelUnlocked($user, $level)) {
            $this->addFlash('info', 'quiz.level_locked');

            return $this->redirectToRoute('app_home');
        }
        $topicCode = $request->request->getString('topic');
        $topic = '' !== $topicCode ? $topics->findOneBy(['code' => $topicCode]) : null;

        $session = $factory->create($user, $level, $topic);
        if (null === $session) {
            $this->addFlash('info', 'quiz.no_questions');

            return $this->redirectToRoute('app_home');
        }

        return $this->redirectToRoute('quiz_show', ['id' => $session->getId()]);
    }

    #[Route('/mistakes', name: 'quiz_mistakes', methods: ['POST'])]
    public function mistakes(Request $request, #[CurrentUser] User $user, QuizFactory $factory): Response
    {
        $this->assertCsrf($request, 'quiz_start');

        $session = $factory->createFromMistakes($user);
        if (null === $session) {
            $this->addFlash('info', 'quiz.no_mistakes');

            return $this->redirectToRoute('app_home');
        }

        return $this->redirectToRoute('quiz_show', ['id' => $session->getId()]);
    }

    #[Route('/{id}', name: 'quiz_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(QuizSession $session): Response
    {
        $this->assertOwner($session);
        if ($session->isFinished()) {
            return $this->redirectToRoute('quiz_summary', ['id' => $session->getId()]);
        }

        $question = $this->currentQuestion($session);
        $options = $question->getOptions();
        shuffle($options);

        return $this->render('quiz/question.html.twig', [
            'session' => $session,
            'question' => $question,
            'options' => $options,
        ]);
    }

    #[Route('/{id}/answer', name: 'quiz_answer', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function answer(Request $request, QuizSession $session, AnswerChecker $checker): Response
    {
        $this->assertOwner($session);
        $this->assertCsrf($request, 'quiz'.$session->getId());

        // Stale form (back button, double click): just show where the quiz really is.
        if ($session->isFinished() || $request->request->getInt('question') !== $session->getCurrentQuestionId()) {
            return $this->redirectToRoute('quiz_show', ['id' => $session->getId()]);
        }

        $question = $this->currentQuestion($session);
        $given = trim($request->request->getString('answer'));
        if ('' === $given) {
            $this->addFlash('info', 'quiz.empty_answer');

            return $this->redirectToRoute('quiz_show', ['id' => $session->getId()]);
        }

        $correct = $checker->isCorrect($question, $given);
        $this->em->persist(new Answer($session->getUser(), $question, $session, $given, $correct));
        $session->advance($correct);
        $this->em->flush();

        return $this->redirectToRoute('quiz_feedback', ['id' => $session->getId()]);
    }

    #[Route('/{id}/feedback', name: 'quiz_feedback', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function feedback(QuizSession $session): Response
    {
        $this->assertOwner($session);
        $answer = $this->answers->findLastAnswerInSession((int) $session->getId());
        if (null === $answer) {
            return $this->redirectToRoute('quiz_show', ['id' => $session->getId()]);
        }

        return $this->render('quiz/feedback.html.twig', ['session' => $session, 'answer' => $answer]);
    }

    #[Route('/{id}/summary', name: 'quiz_summary', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function summary(QuizSession $session, StatsProvider $stats): Response
    {
        $this->assertOwner($session);
        if (!$session->isFinished()) {
            return $this->redirectToRoute('quiz_show', ['id' => $session->getId()]);
        }

        $answers = $this->answers->findBy(['session' => $session], ['id' => 'ASC']);

        // Rank change on every level touched by this quiz (a mistakes quiz can mix levels).
        $levels = $stats->progress($session->getUser())['levels'];
        $rankChanges = [];
        foreach ($answers as $answer) {
            $number = $answer->getQuestion()->getLevel();
            $rankChanges[$number] ??= ['correct' => 0, 'wrong' => 0];
            ++$rankChanges[$number][$answer->isCorrect() ? 'correct' : 'wrong'];
        }
        foreach ($rankChanges as $number => $delta) {
            $level = $levels[$number];
            $before = PlayerRoster::points($level['answers']['correct'] - $delta['correct'], $level['answers']['wrong'] - $delta['wrong']);
            $rankChanges[$number] = [
                'before' => PlayerRoster::rank($before, $level['total']),
                'level' => $level,
            ];
        }
        ksort($rankChanges);

        return $this->render('quiz/summary.html.twig', [
            'session' => $session,
            'answers' => $answers,
            'rank_changes' => $rankChanges,
        ]);
    }

    private function currentQuestion(QuizSession $session): Question
    {
        return $this->questions->find($session->getCurrentQuestionId()) ?? throw $this->createNotFoundException();
    }

    private function assertOwner(QuizSession $session): void
    {
        if ($session->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }
    }

    private function assertCsrf(Request $request, string $id): void
    {
        if (!$this->isCsrfTokenValid($id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }
}
