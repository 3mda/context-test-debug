<?php

namespace ContextTest\Symfony\Bridge\Log;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;
use Monolog\Level;

class TestInMemoryLogHandler extends AbstractProcessingHandler
{
    private array $logs = [];

    public function __construct($level = 100, bool $bubble = true)
    {
        parent::__construct($level, $bubble);
    }

    protected function write(LogRecord|array $record): void
    {
        if ($record instanceof LogRecord) {
            $levelName = $record->level->name;
            $channel = $record->channel;
            $message = $record->message;
            $datetime = $record->datetime;
            $context = $record->context ?? [];
        } else {
            $levelName = $record['level_name'];
            $channel = $record['channel'];
            $message = $record['message'];
            $datetime = $record['datetime'];
            $context = $record['context'] ?? [];
        }

        $this->logs[] = [
            'datetime' => $datetime,
            'level_name' => $levelName,
            'channel' => $channel,
            'message' => $message,
            'context' => is_array($context) ? $context : (array) $context,
        ];
    }

    public function getLogs(): array
    {
        $currentLogs = $this->logs;
        $this->logs = [];
        return $currentLogs;
    }
}
