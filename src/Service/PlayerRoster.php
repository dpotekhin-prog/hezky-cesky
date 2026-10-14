<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Yaml\Yaml;

/**
 * Ranks 0–10 per difficulty level, each represented by an FC Barcelona player (content/players.yaml).
 */
final class PlayerRoster
{
    public const MAX_RANK = 10;

    /** Penalty per wrong answer, in points (a correct answer is worth 1). */
    public const WRONG_PENALTY = 0.5;

    /** @var array<int, array{rank: int, name: string, about: array{cs: string, en: string}}>|null */
    private ?array $players = null;

    public function __construct(
        #[Autowire('%kernel.project_dir%/content/players.yaml')]
        private readonly string $file,
    ) {
    }

    /** @return array{rank: int, name: string, about: array{cs: string, en: string}} */
    public function player(int $rank): array
    {
        if (null === $this->players) {
            $this->players = array_column(Yaml::parseFile($this->file)['players'], null, 'rank');
        }

        return $this->players[$rank] ?? ['rank' => $rank, 'name' => '?', 'about' => ['cs' => '', 'en' => '']];
    }

    public static function points(int $correct, int $wrong): float
    {
        return max(0.0, $correct - self::WRONG_PENALTY * $wrong);
    }

    public static function rank(float $points, int $levelTotal): int
    {
        if ($levelTotal <= 0) {
            return 0;
        }

        return min(self::MAX_RANK, (int) floor(self::MAX_RANK * $points / $levelTotal + 1e-9));
    }

    /** Points still missing to reach the next rank, or null at the top. */
    public static function pointsToNext(float $points, int $levelTotal): ?float
    {
        $rank = self::rank($points, $levelTotal);
        if ($rank >= self::MAX_RANK || $levelTotal <= 0) {
            return null;
        }

        return max(0.5, ceil(2 * (($rank + 1) * $levelTotal / self::MAX_RANK - $points)) / 2);
    }
}
