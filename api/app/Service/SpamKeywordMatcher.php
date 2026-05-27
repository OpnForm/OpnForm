<?php

namespace App\Service;

class SpamKeywordMatcher
{
    public function containsAny(string $content, array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            if ($this->contains($content, (string) $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function contains(string $content, string $keyword): bool
    {
        $keyword = trim($keyword);

        if ($keyword === '') {
            return false;
        }

        $escapedKeyword = preg_quote($keyword, '/');
        $pattern = '/(?<![\pL\pN_])' . $escapedKeyword . '(?![\pL\pN_])/iu';

        return preg_match($pattern, $content) === 1;
    }
}
