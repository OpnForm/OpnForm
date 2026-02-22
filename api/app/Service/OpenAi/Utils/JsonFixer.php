<?php

namespace App\Service\OpenAi\Utils;

/*
 * This file is part of the PHP-JSON-FIXER package.
 *
 * (c) Jitendra Adhikari <jiten.adhikary@gmail.com>
 *     <https://github.com/adhocore>
 *
 * Licensed under MIT license.
 */

use Aws\Exception\InvalidJsonException;
use Illuminate\Support\Facades\Log;

/**
 * Attempts to fix truncated JSON by padding contextual counterparts at the end.
 *
 * @author  Jitendra Adhikari <jiten.adhikary@gmail.com>
 * @license MIT
 *
 * @link    https://github.com/adhocore/php-json-fixer
 */
class JsonFixer
{
    use PadsJson;

    /** @var array Current token stack indexed by position */
    protected $stack = [];

    /** @var bool If current char is within a string */
    protected $inStr = false;

    /** @var bool Whether to throw Exception on failure */
    protected $silent = false;

    /** @var array The complementary pairs */
    protected $pairs = [
        '{' => '}',
        '[' => ']',
        '"' => '"',
    ];

    /** @var int The last seen object `{` type position */
    protected $objectPos = -1;

    /** @var int The last seen array `[` type position */
    protected $arrayPos = -1;

    /** @var string Missing value. (Options: true, false, null) */
    protected $missingValue = 'null';

    /**
     * Set/unset silent mode.
     *
     * @param  bool  $silent
     * @return $this
     */
    public function silent($silent = true)
    {
        $this->silent = (bool) $silent;

        return $this;
    }

    /**
     * Set missing value.
     *
     * @param  mixed  $value
     * @return $this
     */
    public function missingValue($value)
    {
        if ($value === null) {
            $value = 'null';
        } elseif (\is_bool($value)) {
            $value = $value ? 'true' : 'false';
        }

        $this->missingValue = $value;

        return $this;
    }

    /**
     * Fix the truncated JSON.
     *
     * @param  string  $json  The JSON string to fix.
     * @return string Fixed JSON. If failed with silent then original JSON.
     *
     * @throws InvalidJsonException When fixing fails.
     */
    public function fix($json)
    {
        $json = preg_replace('/(?<!\\\\)(?:\\\\{2})*\p{C}+/u', '', $json);
        [$head, $json, $tail] = $this->trim($json);

        if (empty($json) || $this->isValid($json)) {
            return $json;
        }

        if (null !== $tmpJson = $this->quickFix($json)) {
            return $tmpJson;
        }

        $this->reset();

        return $head.$this->doFix($json).$tail;
    }

    protected function trim($json)
    {
        \preg_match('/^(\s*)([^\s]+)(\s*)$/', $json, $match);

        $match += ['', '', '', ''];
        $match[2] = \trim($json);

        \array_shift($match);

        return $match;
    }

    protected function isValid($json)
    {
        \json_decode($json, true, 512, JSON_INVALID_UTF8_SUBSTITUTE);

        return \json_last_error() === \JSON_ERROR_NONE;
    }

    protected function quickFix($json)
    {
        if (\strlen($json) === 1 && isset($this->pairs[$json])) {
            return $json.$this->pairs[$json];
        }

        if ($json[0] !== '"') {
            return $this->maybeLiteral($json);
        }

        return $this->padString($json);
    }

    protected function reset()
    {
        $this->stack = [];
        $this->inStr = false;
        $this->objectPos = -1;
        $this->arrayPos = -1;
    }

    protected function maybeLiteral($json)
    {
        if (! \in_array($json[0], ['t', 'f', 'n'])) {
            return null;
        }

        foreach (['true', 'false', 'null'] as $literal) {
            if (\strpos($literal, $json) === 0) {
                return $literal;
            }
        }

        // @codeCoverageIgnoreStart
        return null;
        // @codeCoverageIgnoreEnd
    }

    protected function doFix($json)
    {
        [$index, $char] = [-1, ''];

        while (isset($json[++$index])) {
            [$prev, $char] = [$char, $json[$index]];

            $next = isset($json[$index + 1]) ? $json[$index + 1] : '';

            if (! \in_array($char, [' ', "\n", "\r"])) {
                $this->stack($prev, $char, $index, $next);
            }
        }

        return $this->fixOrFail($json);
    }

    protected function stack($prev, $char, $index, $next)
    {
        if ($this->maybeStr($prev, $char, $index)) {
            return;
        }

        $last = $this->lastToken();

        if (\in_array($last, [',', ':', '"']) && \preg_match('/\"|\d|\{|\[|t|f|n/', $char)) {
            $this->popToken();
        }

        if (\in_array($char, [',', ':', '[', '{'])) {
            $this->stack[$index] = $char;
        }

        $this->updatePos($char, $index);
    }

    protected function lastToken()
    {
        return \end($this->stack);
    }

    protected function popToken($token = null)
    {
        // Last one
        if ($token === null) {
            return \array_pop($this->stack);
        }

        $keys = \array_reverse(\array_keys($this->stack));
        foreach ($keys as $key) {
            if ($this->stack[$key] === $token) {
                unset($this->stack[$key]);
                break;
            }
        }
    }

    protected function maybeStr($prev, $char, $index)
    {
        if ($prev !== '\\' && $char === '"') {
            $this->inStr = ! $this->inStr;
        }

        if ($this->inStr && $this->lastToken() !== '"') {
            $this->stack[$index] = '"';
        }

        return $this->inStr;
    }

    protected function updatePos($char, $index)
    {
        if ($char === '{') {
            $this->objectPos = $index;
        } elseif ($char === '}') {
            $this->popToken('{');
            $this->objectPos = -1;
        } elseif ($char === '[') {
            $this->arrayPos = $index;
        } elseif ($char === ']') {
            $this->popToken('[');
            $this->arrayPos = -1;
        }
    }

    protected function fixOrFail($json)
    {
        $length = \strlen($json);
        $tmpJson = $this->pad($json);

        if ($this->isValid($tmpJson)) {
            return $tmpJson;
        }

        if ($this->silent) {
            return $json;
        }

        Log::debug('Broken json received: ', [
            'json' => $json,
        ]);

        throw new InvalidJsonException(
            \sprintf('Could not fix JSON (tried padding `%s`): %s', \substr($tmpJson, $length), $json)
        );
    }
}
