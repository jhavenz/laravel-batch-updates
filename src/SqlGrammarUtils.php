<?php

declare(strict_types=1);

namespace Jhavenz\LaravelBatchUpdate;

class SqlGrammarUtils
{
    /**
     * @param $dbDriver
     * @return bool
     */
    public static function disableBacktick($dbDriver): bool
    {
        return in_array($dbDriver, ['pgsql', 'sqlsrv']);
    }

    /**
     * @param $value
     * @return string|string[]
     */
    public static function escape($value)
    {
        if (is_array($value)) {
            return array_map([self::class, 'escape'], $value);
        }

        if (is_bool($value)) {
            return (int) $value;
        }

        if (self::isJson($value)) {
            return self::safeJson($value);
        }

        if (! empty($value) && is_string($value)) {
            return str_replace(
                ['\\', "\0", "\n", "\r", "'", '"', "\x1a"],
                ['\\\\', '\\0', '\\n', '\\r', "\\'", '\\"', '\\Z'],
                $value
            );
        }

        return $value;
    }

    /**
     * @param $str
     * @return bool
     */
    protected static function isJson($str): bool
    {
        if (! is_string($str) || is_numeric($str)) {
            return false;
        }

        $json = json_decode($str);

        return $json && $str != $json;
    }

    /**
     * @param  string  $jsonData
     * @param  bool  $asArray
     * @return array|false|mixed|string
     */
    protected static function safeJson(string $jsonData, bool $asArray = false)
    {
        $jsonData = json_decode($jsonData, true);

        if (json_last_error()) {
            return $jsonData;
        }

        $safeJsonData = [];
        foreach ($jsonData as $key => $value) {
            if (self::isJson($value)) {
                $safeJsonData[$key] = self::safeJson($value, true);
            } elseif (is_string($value)) {
                $safeJsonData[$key] = self::safeJsonString($value);
            } elseif (is_array($value)) {
                $safeJsonData[$key] = self::safeJson(json_encode($value), true);
            } else {
                $safeJsonData[$key] = $value;
            }
        }

        return $asArray ? $safeJsonData : json_encode($safeJsonData, JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param $value
     * @return array|string|string[]
     */
    protected static function safeJsonString($value)
    {
        return str_replace(
            ["'"],
            ["''"],
            $value
        );
    }
}
