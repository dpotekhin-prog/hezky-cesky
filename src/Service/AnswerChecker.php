<?php

namespace App\Service;

use App\Entity\Question;

final class AnswerChecker
{
    public function isCorrect(Question $question, string $given): bool
    {
        if ($question->isChoice()) {
            return $given === $question->getCorrectAnswer();
        }

        $normalized = self::normalize($given);
        if ('' === $normalized) {
            return false;
        }
        foreach ($question->getAnswers() as $accepted) {
            if (self::normalize($accepted) === $normalized) {
                return true;
            }
        }

        return false;
    }

    /**
     * Case-insensitive, whitespace-tolerant comparison. Diacritics stay significant:
     * "dum" is not "dům".
     */
    public static function normalize(string $value): string
    {
        if (class_exists(\Normalizer::class)) {
            $value = \Normalizer::normalize($value, \Normalizer::FORM_C) ?: $value;
        }
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/\s*,\s*/u', ', ', $value);
        $value = preg_replace('/\s+/u', ' ', $value);

        // Ignore a trailing full stop etc., unless the answer itself is a punctuation mark.
        $stripped = rtrim($value, ' .!?');

        return '' === $stripped ? $value : $stripped;
    }
}
