<?php

namespace Tests\Feature;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Tests\TestCase;

class ProxyConfigurationTest extends TestCase
{
    public function test_a_configured_proxy_can_supply_the_original_scheme_and_client_ip(): void
    {
        config(['trustedproxy.proxies' => ['192.0.2.10']]);
        TrustProxies::flushState();
        $request = Request::create('http://scrunote.example/login', server: [
            'REMOTE_ADDR' => '192.0.2.10',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.5',
        ]);
        $response = (new TrustProxies)->handle($request, fn (Request $request) => new JsonResponse([
            'secure' => $request->isSecure(),
            'ip' => $request->ip(),
        ]));

        $this->assertTrue($response->getData()->secure);
        $this->assertSame('198.51.100.5', $response->getData()->ip);
    }

    public function test_an_untrusted_client_cannot_spoof_its_scheme_or_ip(): void
    {
        config(['trustedproxy.proxies' => ['192.0.2.10']]);
        TrustProxies::flushState();
        $request = Request::create('http://scrunote.example/login', server: [
            'REMOTE_ADDR' => '192.0.2.99',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.5',
        ]);
        $response = (new TrustProxies)->handle($request, fn (Request $request) => new JsonResponse([
            'secure' => $request->isSecure(),
            'ip' => $request->ip(),
        ]));

        $this->assertFalse($response->getData()->secure);
        $this->assertSame('192.0.2.99', $response->getData()->ip);
    }
}
