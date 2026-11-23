<?php

namespace App\Services\Payments;

use App\Models\Order;

/** Shared order data both providers need. */
class OrderPayload
{
    public static function amount(float|string $v): string
    {
        return number_format((float) $v, 2, '.', '');
    }

    /** @return array{0:string,1:string} first and last name */
    public static function names(Order $order): array
    {
        $parts = preg_split('/\s+/', trim((string) $order->user->name)) ?: [];
        $first = $parts[0] ?? 'Customer';

        return [$first, trim(implode(' ', array_slice($parts, 1))) ?: $first];
    }

    public static function returnUrl(string $gateway, Order $order, string $status): string
    {
        return route('payments.return', ['gateway' => $gateway, 'order' => $order->id, 'status' => $status]);
    }
}
