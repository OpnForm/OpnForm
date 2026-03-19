<?php

namespace App\Service\Storage;

use Illuminate\Support\Facades\File;

class S3KeyCleaner
{
    public static function sanitize($objectKey, $separator = '-')
    {
        return (new self())->sanitizeS3Key($objectKey, $separator);
    }

    public function replaceLatinCharacters($value)
    {
        // Load the latin map
        $latinMap = json_decode(File::get(resource_path('data/latin-map.json')), true);

        $result = '';
        $length = mb_strlen($value);
        for ($i = 0; $i < $length; $i++) {
            $char = mb_substr($value, $i, 1);
            $result .= array_key_exists($char, $latinMap) ? $latinMap[$char] : $char;
        }
        return $result;
    }

    private function removeIllegalCharacters($value)
    {
        $SAFE_CHARACTERS = '/[^0-9a-zA-Z! _\\.\\*\'\\(\\)\\-\\/]/';
        return preg_replace($SAFE_CHARACTERS, '', $value);
    }

    private function isValidSeparator($separator)
    {
        $SAFE_CHARACTERS = '/[^0-9a-zA-Z! _\\.\\*\'\\(\\)\\-\\/]/';
        return $separator && !preg_match($SAFE_CHARACTERS, $separator);
    }

    public function sanitizeS3Key($objectKey, $separator = '-')
    {
        if (!$this->isValidSeparator($separator)) {
            throw new \Exception("${separator} is not a valid separator");
        }

        if (!$objectKey || (!is_string($objectKey) && !is_numeric($objectKey))) {
            throw new \Exception("Invalid object key: Expected non-empty string or number, received " . (is_object($objectKey) ? get_class($objectKey) : gettype($objectKey)));
        }

        if (is_numeric($objectKey)) {
            return strval($objectKey);
        }

        return str_replace(' ', $separator, $this->removeIllegalCharacters($this->replaceLatinCharacters(trim($objectKey))));
    }
}
