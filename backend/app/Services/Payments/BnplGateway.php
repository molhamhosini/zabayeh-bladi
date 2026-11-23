<?php

namespace App\Services\Payments;

use App\Models\Order;

/** Buy-now-pay-later provider (Tabby, Tamara): hosted checkout, then confirm on return or webhook. */
interface BnplGateway
{
    /** Creates a hosted checkout for the order and returns the URL to open in the app. */
    public function checkoutUrl(Order $order): string;

    /** Asks the provider for the payment state, captures it when approved, and updates the order. Returns true when paid. */
    public function confirm(Order $order): bool;

    public function isConfigured(): bool;
}
