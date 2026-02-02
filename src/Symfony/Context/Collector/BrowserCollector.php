<?php

namespace ContextTest\Symfony\Context\Collector;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class BrowserCollector extends AbstractSymfonyCollector
{
    public function collect(array $context = []): array
    {
        $data = [];
        $request = $context['request'] ?? null;
        $response = $context['response'] ?? null;
        $client = $context['client'] ?? null;

        if (!$request && $client instanceof KernelBrowser) {
            $request = $client->getRequest();
        }
        if (!$response && $client instanceof KernelBrowser) {
            $response = $client->getResponse();
        }

        if ($request instanceof Request) {
            $data['url'] = sprintf('%s %s', $request->getMethod(), $request->getUri());
        }

        if ($response instanceof Response) {
            $data['status'] = $response->getStatusCode();
        }

        return $data;
    }
}
