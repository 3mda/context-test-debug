<?php

namespace ContextTest\Context\Collector;

use ContextTest\Context\PhpErrorLogBuffer;

/**
 * Récupère les erreurs PHP interceptées par le moteur (set_error_handler).
 * Source de vérité : le moteur PHP. Aucune dépendance au fichier error_log.
 */
class PhpErrorLogCollector extends AbstractCollector
{
    public function collect(array $context = []): array
    {
        $captureTime = $context['capture_time'] ?? (new \DateTime())->format('H:i:s.u');
        $entries = PhpErrorLogBuffer::getAndClear();

        if ($entries === []) {
            return [];
        }

        $lines = [];
        foreach ($entries as $e) {
            $lines[] = sprintf('[%s] [%s] %s', $e['time'], $e['level'], $e['message']);
            $lines[] = sprintf('  %s', $e['time']);
            $lines[] = sprintf('  %s (%s:%d)', $e['message'], $e['file'], $e['line']);
        }

        $header = sprintf("[%s] [context-test-debug] PHP errors (moteur, en mémoire)\n", $captureTime);
        $content = $header . implode("\n", $lines);

        return ['content' => $content];
    }
}
