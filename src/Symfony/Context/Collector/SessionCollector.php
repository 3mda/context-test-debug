<?php

namespace ContextTest\Symfony\Context\Collector;

use Symfony\Component\HttpFoundation\Session\SessionInterface;

class SessionCollector extends AbstractSymfonyCollector
{
    public function collect(array $context = []): array
    {
        $data = $this->executeStrategies([
            'RequestObject' => fn() => $this->collectFromRequestObject($context),
        ]);

        $cookies = $this->collectAndPrettyPrintCookies($context);
        if ($cookies !== null) {
            $data['cookies'] = $cookies;
        }

        return $data;
    }

    private function collectFromRequestObject(array $context): ?array
    {
        $client = $context['client'] ?? null;
        if (!$client || !method_exists($client, 'getRequest')) {
            return null;
        }

        $request = $client->getRequest();
        if (!$request) {
            return ['error' => 'No request object in client'];
        }

        if (!method_exists($request, 'hasSession') || !$request->hasSession()) {
            return ['error' => 'Request has no session attached (hasSession=false)'];
        }

        try {
            $session = $request->getSession();
            return $this->extractData($session);
        } catch (\Throwable $e) {
            return ['error' => 'getSession() threw exception: ' . $e->getMessage()];
        }
    }

    private function collectAndPrettyPrintCookies(array $context): ?array
    {
        $client = $context['client'] ?? null;
        if (!$client || !method_exists($client, 'getCookieJar')) {
            return null;
        }

        try {
            $jar = $client->getCookieJar();
            if (!method_exists($jar, 'all')) {
                return null;
            }
            $all = $jar->all();
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }

        $pretty = [];
        foreach ($all as $cookie) {
            if (!is_object($cookie)) {
                continue;
            }
            $name = method_exists($cookie, 'getName') ? $cookie->getName() : '?';
            $value = method_exists($cookie, 'getValue') ? $cookie->getValue() : '';
            $path = method_exists($cookie, 'getPath') ? $cookie->getPath() : '/';
            $domain = method_exists($cookie, 'getDomain') ? $cookie->getDomain() : '';
            $expires = method_exists($cookie, 'getExpiresTime') ? $cookie->getExpiresTime() : null;
            $sameSite = method_exists($cookie, 'getSameSite') ? $cookie->getSameSite() : null;

            $entry = [
                'path' => $path,
                'domain' => $domain ?: '(current)',
                'value' => strlen($value) > 200 ? substr($value, 0, 200) . '… (truncated)' : $value,
            ];
            if ($expires !== null) {
                $entry['expires'] = $expires;
            }
            if ($sameSite !== null) {
                $entry['same_site'] = $sameSite;
            }
            $pretty[$name] = $entry;
        }

        return $pretty ?: null;
    }

    private function extractData(SessionInterface $session): array
    {
        $attributes = $this->formatAttributes($session->all());
        return [
            'id' => $session->getId(),
            'attributes' => $attributes,
            'flash' => $session->getFlashBag()->peekAll(),
        ];
    }

    private function formatAttributes(array $attributes): array
    {
        $formatted = [];
        foreach ($attributes as $key => $value) {
            if (is_scalar($value)) {
                $formatted[$key] = $value;
            } else {
                $formatted[$key] = $this->safePrintR($value);
            }
        }
        return $formatted;
    }

    private function safePrintR($value): string
    {
        if (is_object($value)) {
            return sprintf('Object(%s)', get_class($value));
        }

        if (is_array($value)) {
            $value = array_map(function ($item) {
                return is_object($item) ? sprintf('Object(%s)', get_class($item)) : $item;
            }, $value);
            if (count($value) > 50) {
                $value = array_slice($value, 0, 50, true);
                $value['...'] = ' (Truncated: >50 items)';
            }
        }

        $encoded = json_encode($value, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
        $content = $encoded !== false ? $encoded : print_r($value, true);
        if (strlen($content) > 5000) {
            $content = substr($content, 0, 5000) . "\n... (truncated)";
        }
        return $content;
    }
}
