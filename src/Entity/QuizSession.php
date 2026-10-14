<?php

namespace App\Entity;

use App\Repository\QuizSessionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: QuizSessionRepository::class)]
class QuizSession
{
    public const MODE_NORMAL = 'normal';
    public const MODE_MISTAKES = 'mistakes';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: 'smallint', nullable: true)]
    private ?int $level;

    #[ORM\ManyToOne]
    private ?Topic $topic;

    #[ORM\Column(length: 10)]
    private string $mode;

    /** Ordered question ids for this run. @var list<int> */
    #[ORM\Column]
    private array $questionIds;

    #[ORM\Column]
    private int $position = 0;

    #[ORM\Column]
    private int $correctCount = 0;

    #[ORM\Column]
    private \DateTimeImmutable $startedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $finishedAt = null;

    /** @param list<int> $questionIds */
    public function __construct(User $user, ?int $level, ?Topic $topic, string $mode, array $questionIds)
    {
        $this->user = $user;
        $this->level = $level;
        $this->topic = $topic;
        $this->mode = $mode;
        $this->questionIds = $questionIds;
        $this->startedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getLevel(): ?int
    {
        return $this->level;
    }

    public function getTopic(): ?Topic
    {
        return $this->topic;
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    public function getTotal(): int
    {
        return count($this->questionIds);
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function getCurrentQuestionId(): ?int
    {
        return $this->questionIds[$this->position] ?? null;
    }

    public function getCorrectCount(): int
    {
        return $this->correctCount;
    }

    public function getStartedAt(): \DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function getFinishedAt(): ?\DateTimeImmutable
    {
        return $this->finishedAt;
    }

    public function isFinished(): bool
    {
        return null !== $this->finishedAt;
    }

    public function advance(bool $correct): void
    {
        if ($correct) {
            ++$this->correctCount;
        }
        ++$this->position;
        if ($this->position >= count($this->questionIds)) {
            $this->finishedAt = new \DateTimeImmutable();
        }
    }
}
