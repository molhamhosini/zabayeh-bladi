<?php

namespace App\Services\Payments;

use App\Models\Order;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Tamara: split into instalments.
 * Flow: create checkout -> customer approves -> order "approved" -> we authorise -> we capture -> paid.
 */
class TamaraGateway implements BnplGateway
{
    public function isConfigured(): bool
    {
        return filled(config('shop.tamara.api_token'));
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl(rtrim(config('shop.tamara.base_url'), '/'))->withToken(config('shop.tamara.api_token'))->acceptJson()->timeout(20);
    }

    private function money(float|string $v): array
    {
        return ['amount' => (float) OrderPayload::amount($v), 'currency' => 'AED'];
    }

    public function checkoutUrl(Order $order): string
    {
        $order->loadMissing('user', 'items', 'address.area.emirate');
        [$first, $last] = OrderPayload::names($order);
        $address = [
            'first_name' => $first,
            'last_name' => $last,
            'line1' => $order->address->details,
            'city' => $order->address->area->emirate->name_en,
            'country_code' => 'AE',
            'phone_number' => $order->user->phone,
        ];

        $res = $this->http()->post('/checkout', [
            'order_reference_id' => $order->number,
            'order_number' => $order->number,
            'total_amount' => $this->money($order->total),
            'description' => 'ذبائح بلادي - '.$order->number,
            'country_code' => 'AE',
            'payment_type' => 'PAY_BY_INSTALMENTS',
            'instalments' => config('shop.tamara.instalments'),
            'locale' => $order->user->locale === 'en' ? 'en_US' : 'ar_SA',
            'items' => $order->items->map(fn ($i) => [
                'name' => $i->name_en.($i->variant_en ? ' ('.$i->variant_en.')' : ''),
                'type' => 'Physical',
                'reference_id' => (string) $i->id,
                'sku' => 'P'.$i->product_id.($i->product_variant_id ? '-V'.$i->product_variant_id : ''),
                'quantity' => $i->quantity,
                'unit_price' => $this->money($i->unit_price),
                'total_amount' => $this->money($i->line_total),
            ])->values()->all(),
            'consumer' => [
                'first_name' => $first,
                'last_name' => $last,
                'phone_number' => $order->user->phone,
                'email' => $order->user->email ?: 'customer+'.$order->user_id.'@zabayehbladi.ae',
            ],
            'shipping_address' => $address,
            'billing_address' => $address,
            'tax_amount' => $this->money($order->vat),
            'shipping_amount' => $this->money($order->delivery_fee),
            'merchant_url' => [
                'success' => OrderPayload::returnUrl('tamara', $order, 'success'),
                'failure' => OrderPayload::returnUrl('tamara', $order, 'failure'),
                'cancel' => OrderPayload::returnUrl('tamara', $order, 'cancel'),
                'notification' => route('payments.webhook', ['gateway' => 'tamara']),
            ],
        ]);

        if ($res->failed() || ! $res->json('checkout_url')) {
            throw new BnplException('عذراً، تمارا لا تستطيع الموافقة على هذا الطلب. اختر طريقة دفع أخرى.');
        }
        $order->update(['payment_reference' => $res->json('order_id')]);

        return $res->json('checkout_url');
    }

    public function confirm(Order $order): bool
    {
        if ($order->payment_status === 'paid') {
            return true;
        }
        if (! $order->payment_reference) {
            return false;
        }
        $info = $this->http()->get('/orders/'.$order->payment_reference);
        $status = strtolower((string) $info->json('status'));
        if ($info->successful() && (float) $info->json('total_amount.amount') !== (float) OrderPayload::amount($order->total)) {
            return false;
        }

        if ($status === 'approved') {
            $auth = $this->http()->post('/orders/'.$order->payment_reference.'/authorise');
            $status = $auth->successful() ? 'authorised' : $status;
        }
        if ($status === 'authorised') {
            $capture = $this->http()->post('/payments/capture', [
                'order_id' => $order->payment_reference,
                'total_amount' => $this->money($order->total),
                'shipping_info' => ['shipped_at' => now()->toIso8601String(), 'shipping_company' => 'Zabayeh Bladi'],
                'tax_amount' => $this->money($order->vat),
                'shipping_amount' => $this->money($order->delivery_fee),
            ]);
            $status = $capture->successful() ? 'fully_captured' : $status;
        }

        if (in_array($status, ['fully_captured', 'partially_captured'], true)) {
            $order->update(['payment_status' => 'paid']);

            return true;
        }
        if (in_array($status, ['declined', 'expired', 'canceled'], true)) {
            $order->update(['payment_status' => 'failed']);
        }

        return false;
    }
}
