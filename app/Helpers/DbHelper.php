<?php

namespace App\Helpers;

class DbHelper
{
    /**
     * Execute database query safely with error handling
     * Returns array with 'data' and 'error' keys
     */
    public static function safeQuery(callable $callback): array
    {
        try {
            $data = $callback();
            return [
                'data' => $data ?? [],
                'error' => null,
            ];
        } catch (\Exception $e) {
            return [
                'data' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Execute query and return data or empty array on error
     */
    public static function queryOrEmpty(callable $callback): array
    {
        $result = self::safeQuery($callback);
        return $result['data'] ?? [];
    }

    /**
     * Execute query and return error message or null
     */
    public static function getError(callable $callback): ?string
    {
        $result = self::safeQuery($callback);
        return $result['error'];
    }
}