<?php

declare(strict_types=1);

namespace Dnkmdg\LocalGeoIp;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;

class RequestIpResolver
{
    /**
     * @param array<int, string> $trustedProxies
     * @param array<int, string> $forwardedHeaders
     */
    public function __construct(
        private readonly array $trustedProxies = [],
        private readonly array $forwardedHeaders = [],
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function resolveCandidates(Request $request): array
    {
        $candidates = [];
        $remoteAddress = (string) $request->server->get('REMOTE_ADDR', '');

        if ($remoteAddress !== '' && $this->isTrustedProxy($remoteAddress)) {
            foreach ($this->headers() as $headerName) {
                $headerValue = $request->headers->get($headerName);
                if (! is_string($headerValue) || trim($headerValue) === '') {
                    continue;
                }

                if (strtolower($headerName) === 'x-forwarded-for') {
                    foreach (explode(',', $headerValue) as $part) {
                        $part = trim($part);
                        if ($part !== '') {
                            $candidates[] = $part;
                        }
                    }

                    continue;
                }

                $candidates[] = trim($headerValue);
            }
        }

        if ($remoteAddress !== '') {
            $candidates[] = $remoteAddress;
        }

        return array_values(array_unique($candidates));
    }

    /**
     * @return array<int, string>
     */
    private function headers(): array
    {
        if ($this->forwardedHeaders !== []) {
            return $this->forwardedHeaders;
        }

        return [
            'CF-Connecting-IP',
            'True-Client-IP',
            'X-Forwarded-For',
            'X-Real-IP',
        ];
    }

    private function isTrustedProxy(string $ip): bool
    {
        if ($this->trustedProxies === []) {
            return false;
        }

        if (in_array('*', $this->trustedProxies, true)) {
            return true;
        }

        return IpUtils::checkIp($ip, $this->trustedProxies);
    }
}
