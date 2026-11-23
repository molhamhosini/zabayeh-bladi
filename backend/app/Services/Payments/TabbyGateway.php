<?php

namespace App\Services\Payments;

use App\Models\Order;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Tabby: pay in 4 interest-free instalments.
 * Flow: create checkout session -> customer approves on Tabby's page -> payment AUTHORIZED -> we capture -> CLOSED.
 */
class TabbyGateway implements BnplGateway
{
    public function isConfigured(): bool
    {
        return filled(config('shop.tabby.public_key')) && filled(config('shop.tabby.secret_key')) && filled(config('shop.tabby.merchant_code'));
    }

    private function http(string $key): PendingRequest
    {
        return Http::baseUrl(rtrim(config('shop.tabby.base_url'), '/'))->withToken($key)->acceptJson()->timeout(20);
    }

    public function checkoutUrl(Order $order): string
    {
        $order->loadMissing('user', 'items', 'address.area.emirate');
        $res = $this->http(config('shop.tabby.public_key'))->post('/api/v2/checkout', [
            'payment' => [
                'amount' => OrderPayload::amount($order->total),
                'currency' => 'AED',
                'description' => 'ذبائح بلادي - '.$order->number,
                'buyer' => [
                    'phone' => $order->user->phone,
                    'email' => $order->user->email ?: 'customer+'.$order->user_id.'@zabayehbladi.ae',
                    'name' => $order->user->name,
                ],
                'shipping_address' => [
                    'city' => $order->address->area->emirate->name_en,
                    'address' => $order->address->details,
                    'zip' => '00000',
                ],
                'order' => [
                    'reference_id' => $order->number,
                    'tax_amount' => OrderPayload::amount($order->vat),
                    'shipping_amount' => OrderPayload::amount($order->delivery_fee),
                    'items' => $order->items->map(fn ($i) => [
                        'title' => $i->name_en.($i->variant_en ? ' ('.$i->variant_en.')' : ''),
                        'quantity' => $i->quantity,
                        'unit_price' => OrderPayload::amount($i->unit_price),
                        'reference_id' => (string) $i->product_id,
                        'category' => 'Meat',
                    ])->values()->all(),
                ],
                'buyer_history' => ['registered_since' => $order->user->created_at->toIso8601String(), 'loyalty_level' => 0],
            ],
            'lang' => $order->user->locale === 'en' ? 'en' : 'ar',
            'merchant_code' => config('shop.tabby.merchant_code'),
            'merchant_urls' => [
                'success' => OrderPayload::returnUrl('tabby', $order, 'success'),
                'cancel' => OrderPayload::returnUrl('tabby', $order, 'cancel'),
                'failure' => OrderPayload::returnUrl('tabby', $order, 'failure'),
            ],
        ]);

        if ($res->failed()) {
            throw new BnplException('تعذّر الاتصال بتابي، اختر طريقة دفع أخرى.');
        }
        $url = $res->json('configuration.available_products.installments.0.web_url');
        if ($res->json('status') === 'rejected' || ! $url) {
            throw new BnplException('عذراً، تابي لا يستطيع الموافقة على هذا الطلب. اختر طريقة دفع أخرى.');
        }
        $order->update(['payment_reference' => $res->json('payment.id')]);

        return $url;
    }

    public function confirm(Order $order): bool
    {
        if ($order->payment_status === 'paid') {
            return true;
        }
        if (! $order->payment_reference) {
            return false;
        }
        $secret = $this->http(config('shop.tabby.secret_key'));
        $payment = $secret->get('/api/v2/payments/'.$order->payment_reference);
        $status = strtoupper((string) $payment->json('status'));

        if ($status === 'AUTHORIZED') {
            // Amount must match what we charged; never trust the redirect alone.
            if (OrderPayload::amount($payment->json('amount')) !== OrderPayload::amount($order->total)) {
                return false;
            }
            $capture = $secret->post('/api/v2/payments/'.$order->payment_reference.'/captures', [
                'amount' => OrderPayload::amount($order->total),
                'reference_id' => $order->number,
            ]);
            $status = $capture->successful() ? 'CLOSED' : $status;
        }

        if ($status === 'CLOSED') {
            $order->update(['payment_status' => 'paid']);

            return true;
        }
        if (in_array($status, ['REJECTED', 'EXPIRED'], true)) {
            $order->update(['payment_status' => 'failed']);
        }

        return false;
    }
}
