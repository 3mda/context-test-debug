<?php

namespace ContextTest\Symfony\Bridge\Client;

class TraceableClientFactory
{
    public function create(callable $clientCreator, callable $onResponse): object
    {
        $client = $clientCreator();
        return new ContextTrackingClient($client, $onResponse);
    }
}
