<?php

namespace ContextTest\Symfony\Context\Collector;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Mime\Email;

class MailerCollector extends AbstractSymfonyCollector
{
    public function collect(array $context = []): array
    {
        $client = $context['client'] ?? null;
        if ($client && method_exists($client, 'getContainer')) {
            $container = $client->getContainer();
        } else {
            $container = $context['container'] ?? null;
        }

        if (!$container instanceof ContainerInterface) {
            return [];
        }

        $serviceId = null;
        if ($container->has('mailer.message_logger_listener')) {
            $serviceId = 'mailer.message_logger_listener';
        } elseif ($container->has('mailer.logger_message_listener')) {
            $serviceId = 'mailer.logger_message_listener';
        } else {
            return [];
        }

        try {
            $logger = $container->get($serviceId);
            if (!method_exists($logger, 'getEvents')) {
                return [];
            }
            $messages = $logger->getEvents()->getMessages();
            if (empty($messages)) {
                return [];
            }
            return ['messages' => $this->deduplicateMessages($messages)];
        } catch (\Throwable $e) {
            return ['error' => 'Could not collect emails: ' . $e->getMessage()];
        }
    }

    private function deduplicateMessages(array $messages): array
    {
        $data = [];
        $seen = [];
        foreach ($messages as $message) {
            if ($message instanceof Email) {
                $body = $message->getHtmlBody() ?? $message->getTextBody();
                $signature = md5(serialize([$message->getSubject(), $message->getTo(), $body]));
                if (isset($seen[$signature])) {
                    continue;
                }
                $seen[$signature] = true;
                $data[] = [
                    'subject' => $message->getSubject(),
                    'to' => implode(', ', array_map(fn($a) => $a->toString(), $message->getTo())),
                    'body' => $body,
                ];
            }
        }
        return $data;
    }
}
