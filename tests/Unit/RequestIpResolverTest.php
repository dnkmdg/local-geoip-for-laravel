<?php

declare(strict_types=1);

namespace Dnkmdg\LocalGeoIp\Tests\Unit;

use Illuminate\Http\Request;
use Dnkmdg\LocalGeoIp\RequestIpResolver;
use Dnkmdg\LocalGeoIp\Tests\TestCase;

final class RequestIpResolverTest extends TestCase
{
    public function test_uses_forwarded_headers_only_for_trusted_proxy(): void
    {
        $request = Request::create('/', 'GET', server: [
            'REMOTE_ADDR' => '10.0.0.1',
            'HTTP_CF_CONNECTING_IP' => '3.3.3.3',
            'HTTP_TRUE_CLIENT_IP' => '4.4.4.4',
            'HTTP_X_FORWARDED_FOR' => '1.1.1.1, 2.2.2.2',
            'HTTP_X_REAL_IP' => '5.5.5.5',
        ]);

        $resolver = new RequestIpResolver(
            trustedProxies: ['10.0.0.0/8'],
            forwardedHeaders: ['CF-Connecting-IP', 'True-Client-IP', 'X-Forwarded-For', 'X-Real-IP'],
        );

        $this->assertSame(
            ['3.3.3.3', '4.4.4.4', '1.1.1.1', '2.2.2.2', '5.5.5.5', '10.0.0.1'],
            $resolver->resolveCandidates($request),
        );
    }

    public function test_ignores_forwarded_headers_for_untrusted_remote_address(): void
    {
        $request = Request::create('/', 'GET', server: [
            'REMOTE_ADDR' => '203.0.113.10',
            'HTTP_X_FORWARDED_FOR' => '1.1.1.1, 2.2.2.2',
        ]);

        $resolver = new RequestIpResolver(
            trustedProxies: ['10.0.0.0/8'],
            forwardedHeaders: ['X-Forwarded-For'],
        );

        $this->assertSame(['203.0.113.10'], $resolver->resolveCandidates($request));
    }
}
