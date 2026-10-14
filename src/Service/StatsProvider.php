<?php

namespace App\Service;

use App\Entity\Question;
use App\Entity\User;
use App\Repository\AnswerRepository;
use App\Repository\QuestionRepository;
use App\Repository\QuizSessionRepository;
use App\Repository\TopicRepository;

final class StatsProvider
{
    /** Next level opens when this share of the previous level's questions has been answered… */
    public const UNLOCK_DONE_SHARE = 0.8;
    /** …with at least this success rate (percent) on that level. */
    public const UNLOCK_MIN_PERCENT = 70;

    public function __construct(
        private readonly AnswerRepository $answers,
        private readonly TopicRepository $topics,
        private readonly QuizSessionRepository $sessions,
        private readonly QuestionRepository $questions,
        private readonly PlayerRoster $roster,
    ) {
    }

    /** @return array{total: int, correct: int, wrong: int, percent: ?int} */
    public function summary(User $user): array
    {
        return self::withPercent($this->answers->totals($user));
    }

    /**
     * Per level: how many questions exist / were answered (also per topic), answer totals,
     * rank 0–10 with its player, and whether the level is unlocked.
     */
    public function progress(User $user): array
    {
        $answered = $this->answers->answeredQuestionIds($user);
        $byLevel = $this->answers->totalsByLevel($user);

        $result = ['total' => 0, 'done' => 0, 'levels' => []];
        foreach (Question::LEVELS as $level) {
            $result['levels'][$level] = ['total' => 0, 'done' => 0, 'topics' => []];
        }
        foreach ($this->questions->findActiveMeta() as $q) {
            $done = isset($answered[$q['id']]) ? 1 : 0;
            $level = &$result['levels'][$q['level']];
            $level['topics'][$q['topic']] ??= ['total' => 0, 'done' => 0];
            ++$level['topics'][$q['topic']]['total'];
            $level['topics'][$q['topic']]['done'] += $done;
            ++$level['total'];
            $level['done'] += $done;
            ++$result['total'];
            $result['done'] += $done;
            unset($level);
        }

        $previous = null;
        foreach ($result['levels'] as $number => &$level) {
            $level['answers'] = self::withPercent($byLevel[$number] ?? ['total' => 0, 'correct' => 0]);
            $level['points'] = PlayerRoster::points($level['answers']['correct'], $level['answers']['wrong']);
            $level['rank'] = PlayerRoster::rank($level['points'], $level['total']);
            $level['player'] = $this->roster->player($level['rank']);
            $level['to_next'] = PlayerRoster::pointsToNext($level['points'], $level['total']);
            // Once a child has started a level it stays open, even if new questions are added later.
            $level['unlocked'] = null === $previous || $level['answers']['total'] > 0 || self::meetsUnlock($previous);
            if (null !== $previous) {
                $level['unlock_need'] = [
                    'done' => (int) ceil(self::UNLOCK_DONE_SHARE * $previous['total']),
                    'done_now' => $previous['done'],
                    'percent' => self::UNLOCK_MIN_PERCENT,
                    'percent_now' => $previous['answers']['percent'] ?? 0,
                ];
            }
            $previous = $level;
        }
        unset($level);

        return $result;
    }

    public function isLevelUnlocked(User $user, int $level): bool
    {
        return $this->progress($user)['levels'][$level]['unlocked'] ?? false;
    }

    /** Everything the stats page needs. */
    public function full(User $user): array
    {
        $byTopic = $this->answers->totalsByTopic($user);
        $topics = [];
        foreach ($this->topics->findAllOrdered() as $topic) {
            $topics[] = ['topic' => $topic] + self::withPercent($byTopic[$topic->getId()] ?? ['total' => 0, 'correct' => 0]);
        }

        return [
            'summary' => $this->summary($user),
            'progress' => $this->progress($user),
            'topics' => $topics,
            'mistakes' => $this->answers->findOpenMistakes($user),
            'sessions' => $this->sessions->findRecent($user),
        ];
    }

    private static function meetsUnlock(array $level): bool
    {
        return $level['total'] > 0
            && $level['done'] >= ceil(self::UNLOCK_DONE_SHARE * $level['total'])
            && ($level['answers']['percent'] ?? 0) >= self::UNLOCK_MIN_PERCENT;
    }

    /**
     * @param array{total: int, correct: int} $row
     *
     * @return array{total: int, correct: int, wrong: int, percent: ?int}
     */
    private static function withPercent(array $row): array
    {
        return $row + [
            'wrong' => $row['total'] - $row['correct'],
            'percent' => $row['total'] > 0 ? (int) round(100 * $row['correct'] / $row['total']) : null,
        ];
    }
}
