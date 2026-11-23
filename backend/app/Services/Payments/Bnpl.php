<?php

namespace App\Services\Payments;

class Bnpl
{
    public const GATEWAYS = ['tabby' => TabbyGateway::class, 'tamara' => TamaraGateway::class];

    public static function for(string $method): BnplGateway
    {
        abort_unless(isset(self::GATEWAYS[$method]), 404);

        return app(self::GATEWAYS[$method]);
    }

    public static function isBnpl(string $method): bool
    {
        return isset(self::GATEWAYS[$method]);
    }
}
