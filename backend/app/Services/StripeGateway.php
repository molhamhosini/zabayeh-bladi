<?php

namespace App\Services;

use App\Models\Order;
use Stripe\StripeClient;

class StripeGateway
{
    public function __construct(private ?StripeClient $client = null) {}

    private function client(): StripeClient
    {
        return $this->client ??= new StripeClient((string) config('shop.stripe.secret'));
    }

    /** Creates (or reuses) a PaymentIntent for the order and returns its client secret for the app. */
    public function clientSecretFor(Order $order): string
    {
        if ($order->stripe_payment_intent_id) {
            return $this->client()->paymentIntents->retrieve($order->stripe_payment_intent_id)->client_secret;
        }

        $intent = $this->client()->paymentIntents->create([
            'amount' => (int) round($order->total * 100), // AED -> fils
            'currency' => config('shop.currency'),
            'automatic_payment_methods' => ['enabled' => true],
            'metadata' => ['order_id' => $order->id, 'order_number' => $order->number],
        ], ['idempotency_key' => 'order-'.$order->id]);

        $order->update(['stripe_payment_intent_id' => $intent->id]);

        return $intent->client_secret;
    }
}
