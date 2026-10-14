<?php

namespace App\Entity;

use App\Repository\AnswerRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AnswerRepository::class)]
#[ORM\Index(columns: ['answered_at'])]
class Answer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Question $question;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private QuizSession $session;

    #[ORM\Column(length: 255)]
    private string $givenAnswer;

    #[ORM\Column]
    private bool $correct;

    #[ORM\Column]
    private \DateTimeImmutable $answeredAt;

    public function __construct(User $user, Question $question, QuizSession $session, string $givenAnswer, bool $correct)
    {
        $this->user = $user;
        $this->question = $question;
        $this->session = $session;
        $this->givenAnswer = mb_substr($givenAnswer, 0, 255);
        $this->correct = $correct;
        $this->answeredAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getQuestion(): Question
    {
        return $this->question;
    }

    public function getSession(): QuizSession
    {
        return $this->session;
    }

    public function getGivenAnswer(): string
    {
        return $this->givenAnswer;
    }

    public function isCorrect(): bool
    {
        return $this->correct;
    }

    public function getAnsweredAt(): \DateTimeImmutable
    {
        return $this->answeredAt;
    }
}
