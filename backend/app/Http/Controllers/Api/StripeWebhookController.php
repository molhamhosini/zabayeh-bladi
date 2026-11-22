<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    public function __invoke(Request $request): Response
    {
        try {
            $event = Webhook::constructEvent($request->getContent(), (string) $request->header('Stripe-Signature'), (string) config('shop.stripe.webhook_secret'));
        } catch (SignatureVerificationException|\UnexpectedValueException) {
            return response('invalid signature', 400);
        }

        $intent = $event->data->object;
        $order = Order::where('stripe_payment_intent_id', $intent->id ?? null)->first();
        if (! $order) {
            return response('ignored', 200);
        }

        match ($event->type) {
            'payment_intent.succeeded' => $order->payment_status !== 'paid' && (int) $intent->amount_received === (int) round($order->total * 100)
                ? $order->update(['payment_status' => 'paid']) : null,
            'payment_intent.payment_failed' => $order->payment_status === 'unpaid' ? $order->update(['payment_status' => 'failed']) : null,
            'charge.refunded' => $order->update(['payment_status' => 'refunded']),
            default => null,
        };

        return response('ok', 200);
    }
}
