<?php

namespace App\Entity;

use App\Repository\QuestionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: QuestionRepository::class)]
#[ORM\Index(columns: ['level', 'active'])]
class Question
{
    public const TYPE_CHOICE = 'choice';
    public const TYPE_INPUT = 'input';

    public const LEVELS = [1, 2, 3];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Stable id from the YAML question bank. */
    #[ORM\Column(length: 50, unique: true)]
    private string $externalId;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Topic $topic;

    #[ORM\Column(type: 'smallint')]
    private int $level = 1;

    #[ORM\Column(length: 10)]
    private string $type = self::TYPE_CHOICE;

    #[ORM\Column(type: 'text')]
    private string $prompt = '';

    /** @var list<string> */
    #[ORM\Column]
    private array $options = [];

    /** For choice: [correct option]; for input: all accepted answers. @var list<string> */
    #[ORM\Column]
    private array $answers = [];

    #[ORM\Column(type: 'text')]
    private string $explanationCs = '';

    #[ORM\Column(type: 'text')]
    private string $explanationEn = '';

    /** False when the question was removed from the YAML bank (kept for answer history). */
    #[ORM\Column]
    private bool $active = true;

    public function __construct(string $externalId, Topic $topic)
    {
        $this->externalId = $externalId;
        $this->topic = $topic;
    }

    /**
     * @param list<string> $options
     * @param list<string> $answers
     */
    public function update(Topic $topic, int $level, string $type, string $prompt, array $options, array $answers, string $explanationCs, string $explanationEn): void
    {
        $this->topic = $topic;
        $this->level = $level;
        $this->type = $type;
        $this->prompt = $prompt;
        $this->options = $options;
        $this->answers = $answers;
        $this->explanationCs = $explanationCs;
        $this->explanationEn = $explanationEn;
        $this->active = true;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getExternalId(): string
    {
        return $this->externalId;
    }

    public function getTopic(): Topic
    {
        return $this->topic;
    }

    public function getLevel(): int
    {
        return $this->level;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function isChoice(): bool
    {
        return self::TYPE_CHOICE === $this->type;
    }

    public function getPrompt(): string
    {
        return $this->prompt;
    }

    /** @return list<string> */
    public function getOptions(): array
    {
        return $this->options;
    }

    /** @return list<string> */
    public function getAnswers(): array
    {
        return $this->answers;
    }

    /** The canonical answer shown to the child after a mistake. */
    public function getCorrectAnswer(): string
    {
        return $this->answers[0] ?? '';
    }

    public function getExplanation(string $locale): string
    {
        return 'en' === $locale ? $this->explanationEn : $this->explanationCs;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function deactivate(): void
    {
        $this->active = false;
    }
}
