<?php

namespace App\Service;

use App\Entity\Question;
use App\Entity\Topic;
use App\Repository\QuestionRepository;
use App\Repository\TopicRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

/**
 * Syncs content/topics.yaml and content/questions/*.yaml into the database.
 * Questions are matched by their YAML id; ones missing from YAML are deactivated, not deleted,
 * so answer history stays intact.
 */
final class QuestionBankImporter
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TopicRepository $topics,
        private readonly QuestionRepository $questions,
        #[Autowire('%kernel.project_dir%/content')]
        private readonly string $contentDir,
    ) {
    }

    /** @return array{topics: int, questions: int, deactivated: int} */
    public function import(): array
    {
        $topicsByCode = $this->importTopics();

        $data = [];
        $finder = (new Finder())->files()->in($this->contentDir.'/questions')->name('*.yaml')->sortByName();
        foreach ($finder as $file) {
            foreach (Yaml::parseFile($file->getPathname())['questions'] ?? [] as $i => $row) {
                $this->validate($row, $topicsByCode, sprintf('%s #%d', $file->getFilename(), $i + 1));
                if (isset($data[$row['id']])) {
                    throw new \RuntimeException(sprintf('Duplicate question id "%s" in %s', $row['id'], $file->getFilename()));
                }
                $data[$row['id']] = $row;
            }
        }

        $existing = [];
        foreach ($this->questions->findAll() as $question) {
            $existing[$question->getExternalId()] = $question;
        }

        foreach ($data as $id => $row) {
            $topic = $topicsByCode[$row['topic']];
            $question = $existing[$id] ?? new Question($id, $topic);
            $isChoice = Question::TYPE_CHOICE === $row['type'];
            $question->update(
                $topic,
                (int) $row['level'],
                $row['type'],
                trim($row['prompt']),
                $isChoice ? array_map('strval', $row['options']) : [],
                $isChoice ? [(string) $row['correct']] : array_map('strval', $row['answers']),
                trim($row['explanation']['cs'] ?? ''),
                trim($row['explanation']['en'] ?? ''),
            );
            $this->em->persist($question);
        }

        $deactivated = 0;
        foreach (array_diff_key($existing, $data) as $question) {
            if ($question->isActive()) {
                $question->deactivate();
                ++$deactivated;
            }
        }

        $this->em->flush();

        return ['topics' => count($topicsByCode), 'questions' => count($data), 'deactivated' => $deactivated];
    }

    /** @return array<string, Topic> */
    private function importTopics(): array
    {
        $byCode = [];
        foreach ($this->topics->findAll() as $topic) {
            $byCode[$topic->getCode()] = $topic;
        }

        $result = [];
        foreach (Yaml::parseFile($this->contentDir.'/topics.yaml')['topics'] as $position => $row) {
            $topic = $byCode[$row['code']] ?? new Topic($row['code']);
            $topic->update($row['title']['cs'], $row['title']['en'], $position);
            $this->em->persist($topic);
            $result[$row['code']] = $topic;
        }

        return $result;
    }

    /** @param array<string, Topic> $topics */
    private function validate(mixed $row, array $topics, string $where): void
    {
        $fail = static fn (string $msg) => throw new \RuntimeException(sprintf('%s: %s', $where, $msg));

        if (!is_array($row) || !isset($row['id'], $row['topic'], $row['level'], $row['type'], $row['prompt'])) {
            $fail('id, topic, level, type and prompt are required');
        }
        if (!isset($topics[$row['topic']])) {
            $fail(sprintf('unknown topic "%s"', $row['topic']));
        }
        if (!in_array((int) $row['level'], Question::LEVELS, true)) {
            $fail('level must be 1, 2 or 3');
        }
        switch ($row['type']) {
            case Question::TYPE_CHOICE:
                if (!is_array($row['options'] ?? null) || count($row['options']) < 2) {
                    $fail('choice needs at least 2 options');
                }
                if (!in_array((string) ($row['correct'] ?? ''), array_map('strval', $row['options']), true)) {
                    $fail('"correct" must be one of the options');
                }
                break;
            case Question::TYPE_INPUT:
                if (!is_array($row['answers'] ?? null) || [] === $row['answers']) {
                    $fail('input needs a non-empty "answers" list');
                }
                break;
            default:
                $fail(sprintf('unknown type "%s"', $row['type']));
        }
    }
}
