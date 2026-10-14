<?php

namespace App\Entity;

use App\Repository\TopicRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TopicRepository::class)]
class Topic
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    private string $code;

    #[ORM\Column(length: 150)]
    private string $titleCs = '';

    #[ORM\Column(length: 150)]
    private string $titleEn = '';

    #[ORM\Column]
    private int $position = 0;

    public function __construct(string $code)
    {
        $this->code = $code;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getTitle(string $locale): string
    {
        return 'en' === $locale ? $this->titleEn : $this->titleCs;
    }

    public function update(string $titleCs, string $titleEn, int $position): void
    {
        $this->titleCs = $titleCs;
        $this->titleEn = $titleEn;
        $this->position = $position;
    }
}
