<?php

namespace ContextTest\Context;

/**
 * Buffer en mémoire des erreurs PHP interceptées par le moteur (set_error_handler).
 * Source de vérité : le moteur PHP, pas le fichier error_log.
 */
final class PhpErrorLogBuffer
{
    /** @var list<array{time: string, level: string, message: string, file: string, line: int}> */
    private static array $entries = [];

    public static function push(int $severity, string $message, string $file, int $line): void
    {
        $t = microtime(true);
        $dt = new \DateTime('now', new \DateTimeZone(date_default_timezone_get() ?: 'UTC'));
        $dt->setTimestamp((int) $t);
        $us = (int) (($t - (int) $t) * 1_000_000);
        $dt->setTime(
            (int) $dt->format('H'),
            (int) $dt->format('i'),
            (int) $dt->format('s'),
            $us
        );
        $time = $dt->format('H:i:s.u');
        $level = self::severityToLabel($severity);
        self::$entries[] = [
            'time' => $time,
            'level' => $level,
            'message' => $message,
            'file' => $file,
            'line' => $line,
        ];
    }

    public static function getAndClear(): array
    {
        $entries = self::$entries;
        self::$entries = [];

        return $entries;
    }

    public static function getEntries(): array
    {
        return self::$entries;
    }

    private static function severityToLabel(int $severity): string
    {
        return match ($severity) {
            E_ERROR => 'Error',
            E_WARNING => 'Warning',
            E_PARSE => 'Parse',
            E_NOTICE => 'Notice',
            E_CORE_ERROR => 'Core Error',
            E_CORE_WARNING => 'Core Warning',
            E_COMPILE_ERROR => 'Compile Error',
            E_COMPILE_WARNING => 'Compile Warning',
            E_USER_ERROR => 'User Error',
            E_USER_WARNING => 'User Warning',
            E_USER_NOTICE => 'User Notice',
            E_STRICT => 'Strict',
            E_RECOVERABLE_ERROR => 'Recoverable Error',
            E_DEPRECATED => 'Deprecated',
            E_USER_DEPRECATED => 'User Deprecated',
            default => 'Unknown',
        };
    }
}
