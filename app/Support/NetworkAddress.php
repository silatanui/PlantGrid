<?php

namespace App\Support;

class NetworkAddress
{
    public static function contains(string $ip, string $network, int $cidr): bool
    {
        $ipLong = ip2long($ip);
        $networkLong = ip2long($network);

        if ($ipLong === false || $networkLong === false || $cidr < 0 || $cidr > 32) {
            return false;
        }

        $mask = $cidr === 0 ? 0 : ((-1 << (32 - $cidr)) & 0xFFFFFFFF);

        return (($ipLong & $mask) === ($networkLong & $mask));
    }
}
