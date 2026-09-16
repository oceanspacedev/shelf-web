<?php

namespace Tests\Unit;

use App\Services\WhatsAppGateway;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Tests\TestCase;

class WhatsAppGatewayTest extends TestCase
{
    public function test_it_sends_whatsapp_message_via_waghub(): void
    {
        config()->set('services.whatsapp_gateway.url', 'https://waghub.mekayastudio.com');
        config()->set('services.whatsapp_gateway.token', 'wag-secret');
        config()->set('services.whatsapp_gateway.country_code', '62');

        $history = [];
        $client = $this->clientWithResponses([
            new Response(201, [], '{"id":"msg-1"}'),
        ], $history);

        $gateway = new WhatsAppGateway($client);

        $this->assertTrue($gateway->send('+62 812-3456-7890', 'Halo'));
        $this->assertCount(1, $history);

        $request = $history[0]['request'];

        $this->assertSame('https://waghub.mekayastudio.com/api/v1/messages', (string) $request->getUri());
        $this->assertSame('Bearer wag-secret', $request->getHeaderLine('Authorization'));

        $payload = json_decode((string) $request->getBody(), true);
        $this->assertSame('phone', $payload['recipient']['type']);
        $this->assertSame('6281234567890', $payload['recipient']['value']);
        $this->assertSame('text', $payload['message']['type']);
        $this->assertSame('Halo', $payload['message']['text']);
        $this->assertSame('shelf', $payload['client_reference']);
    }

    public function test_it_fails_without_wag_token(): void
    {
        config()->set('services.whatsapp_gateway.url', 'https://waghub.mekayastudio.com');
        config()->set('services.whatsapp_gateway.token', '');

        $gateway = new WhatsAppGateway;

        $this->assertFalse($gateway->send('081234567890', 'Halo'));
    }

    public function test_it_normalizes_local_indonesian_phone_numbers(): void
    {
        config()->set('services.whatsapp_gateway.country_code', '62');

        $gateway = new WhatsAppGateway;

        $this->assertSame('6281234567890', $gateway->normalizeTarget('0812-3456-7890'));
        $this->assertSame('6281234567890', $gateway->normalizeTarget('81234567890'));
    }

    private function clientWithResponses(array $responses, array &$history): Client
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($history));

        return new Client([
            'handler' => $stack,
        ]);
    }
}
