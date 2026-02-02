<?php

namespace ContextTest\Context\Report;

/**
 * Génération du rapport de contexte en texte structuré indenté (core PHP 8, sans dépendance Symfony).
 */
class ReportGenerator
{
    private const INDENT = 2;

    public function generate(array $reportData): string
    {
        return $this->formatBlock($reportData, 0);
    }

    public function save(string $content, string $filePath): void
    {
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($filePath, "---\n" . $content . "\n", FILE_APPEND);
    }

    private function formatBlock(array $data, int $level): string
    {
        $pad = str_repeat(' ', $level * self::INDENT);
        $lines = [];

        foreach ($data as $key => $value) {
            if ($value === null) {
                $lines[] = $pad . $key . ':';
                continue;
            }
            if (is_scalar($value)) {
                $str = (string) $value;
                if (str_contains($str, "\n")) {
                    $lines[] = $pad . $key . ':';
                    foreach (explode("\n", $str) as $line) {
                        $lines[] = $pad . str_repeat(' ', self::INDENT) . $this->sanitizeInline($line);
                    }
                } else {
                    $lines[] = $pad . $key . ': ' . $this->sanitizeInline($str);
                }
                continue;
            }
            if (is_array($value)) {
                $lines[] = $pad . $key . ':';
                $lines[] = $this->formatBlockOrList($value, $level + 1);
                continue;
            }
            $lines[] = $pad . $key . ': ' . $this->sanitizeInline((string) $value);
        }

        return implode("\n", $lines);
    }

    private function formatBlockOrList(array $data, int $level): string
    {
        $pad = str_repeat(' ', $level * self::INDENT);
        $isList = array_is_list($data);

        if ($isList) {
            $lines = [];
            foreach ($data as $item) {
                if (is_scalar($item)) {
                    $str = (string) $item;
                    if (str_contains($str, "\n")) {
                        $parts = explode("\n", $str);
                        $lines[] = $pad . '- ' . $this->sanitizeInline($parts[0]);
                        $subPad = $pad . str_repeat(' ', self::INDENT);
                        for ($i = 1; $i < count($parts); $i++) {
                            $lines[] = $subPad . $this->sanitizeInline($parts[$i]);
                        }
                    } else {
                        $lines[] = $pad . '- ' . $this->sanitizeInline($str);
                    }
                } elseif (is_array($item)) {
                    $lines[] = $pad . '-';
                    $lines[] = $this->formatBlock($item, $level + 1);
                } else {
                    $lines[] = $pad . '- ' . $this->sanitizeInline((string) $item);
                }
            }
            return implode("\n", $lines);
        }

        return $this->formatBlock($data, $level);
    }

    private function sanitizeInline(string $s): string
    {
        $s = str_replace(["\r\n", "\r", "\n"], ' ', $s);
        if (strlen($s) > 2000) {
            $s = substr($s, 0, 2000) . '... (truncated)';
        }
        return $s;
    }
}
