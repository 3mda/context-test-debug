<?php

namespace ContextTest\Symfony\Bridge\Client;

use Symfony\Component\BrowserKit\CookieJar;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Proxy autour du client de test pour logger chaque action.
 * getRequest() / getResponse() retournent ?object (KernelBrowser ou Api Platform).
 */
class ContextTrackingClient
{
    private object $innerClient;
    private $onAction;

    public function __construct(object $innerClient, callable $onAction)
    {
        $this->innerClient = $innerClient;
        $this->onAction = $onAction;
    }

    public function request(string $method, string $uri, array $parameters = [], array $files = [], array $server = [], string $content = null, bool $changeHistory = true): ?object
    {
        $crawler = $this->innerClient->request($method, $uri, $parameters, $files, $server, $content, $changeHistory);
        $this->triggerHook();
        return $crawler;
    }

    public function submit($form, array $values = [], array $serverParameters = []): object
    {
        $crawler = $this->innerClient->submit($form, $values, $serverParameters);
        $this->triggerHook();
        return $crawler;
    }

    public function submitForm(string $button, array $fieldValues = [], string $method = 'POST', array $serverParameters = []): object
    {
        $crawler = $this->innerClient->submitForm($button, $fieldValues, $method, $serverParameters);
        $this->triggerHook();
        return $crawler;
    }

    public function followRedirect(): object
    {
        $crawler = $this->innerClient->followRedirect();
        $this->triggerHook();
        return $crawler;
    }

    public function getContainer(): ?ContainerInterface
    {
        return method_exists($this->innerClient, 'getContainer') ? $this->innerClient->getContainer() : null;
    }

    public function getCookieJar(): CookieJar
    {
        return $this->innerClient->getCookieJar();
    }

    public function getRequest(): ?object
    {
        return method_exists($this->innerClient, 'getRequest') ? $this->innerClient->getRequest() : null;
    }

    public function getResponse(): ?object
    {
        return method_exists($this->innerClient, 'getResponse') ? $this->innerClient->getResponse() : null;
    }

    public function getCrawler(): ?object
    {
        return $this->innerClient->getCrawler();
    }

    public function __call($name, $arguments)
    {
        return $this->innerClient->$name(...$arguments);
    }

    private function triggerHook(): void
    {
        $request = $this->getRequest();
        $response = $this->getResponse();
        ($this->onAction)($request, $response, $this->innerClient);
    }
}
