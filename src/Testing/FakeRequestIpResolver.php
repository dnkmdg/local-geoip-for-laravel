<?php

declare(strict_types=1);

namespace Dnkmdg\LocalGeoIp\Testing;

use Dnkmdg\LocalGeoIp\RequestIpResolver;
use Illuminate\Http\Request;

final class FakeRequestIpResolver extends RequestIpResolver
{
    /**
     * @param array<int, string> $ips
     */
    public function __construct(
        private array $ips,
    ) {
        parent::__construct([], []);
    }

    /**
     * @return array<int, string>
     */
    public function resolveCandidates(Request $request): array
    {
        return $this->ips;
    }
}
