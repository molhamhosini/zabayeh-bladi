<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\Payments\Bnpl;
use App\Services\Payments\BnplException;
use App\Services\StripeGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json($request->user()->orders()->with('branch:id,name_ar,name_en', 'items')->latest()->paginate(20));
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        abort_unless($order->user_id === $request->user()->id, 404);

        return response()->json($order->load('items', 'branch.vendor', 'address.area.emirate', 'slot', 'statusLogs'));
    }

    public function store(Request $request, OrderService $orders, StripeGateway $stripe): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => ['required', 'integer'],
            'address_id' => ['required', 'integer'],
            'delivery_slot_id' => ['nullable', 'integer'],
            'purpose' => ['nullable', Rule::in(Order::PURPOSES)],
            'beneficiary_name' => ['nullable', 'required_if:purpose,aqiqah', 'string', 'max:100'],
            'donate_to_charity' => ['boolean'],
            'payment_method' => ['required', Rule::in(['cod', 'card', 'tabby', 'tamara'])],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.variant_id' => ['nullable', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:20'],
            'items.*.option_ids' => ['array'],
            'items.*.option_ids.*' => ['integer'],
        ]);

        if (Bnpl::isBnpl($data['payment_method']) && ! Bnpl::for($data['payment_method'])->isConfigured()) {
            return response()->json(['message' => 'طريقة الدفع غير متاحة حالياً', 'errors' => ['payment_method' => ['طريقة الدفع غير متاحة حالياً']]], 422);
        }

        $order = $orders->place($request->user(), $data);

        return response()->json(['order' => $order] + $this->paymentInstructions($order, $stripe), 201);
    }

    /** What the app must do next to collect payment: Stripe sheet or hosted Tabby/Tamara page. */
    private function paymentInstructions(Order $order, StripeGateway $stripe): array
    {
        if ($order->payment_method === 'card') {
            return ['stripe' => ['client_secret' => $stripe->clientSecretFor($order), 'publishable_key' => config('shop.stripe.publishable')]];
        }
        if (Bnpl::isBnpl($order->payment_method)) {
            try {
                return ['checkout_url' => Bnpl::for($order->payment_method)->checkoutUrl($order)];
            } catch (BnplException $e) {
                // The order stays unpaid; the app shows the reason and lets the customer pay another way.
                return ['payment_error' => $e->getMessage()];
            } catch (\Throwable $e) {
                // Network/provider outage: the order exists, so never answer 500 (the app would retry and duplicate it).
                report($e);

                return ['payment_error' => 'تعذّر الاتصال بخدمة الدفع، يمكنك إعادة المحاولة من صفحة الطلب.'];
            }
        }

        return [];
    }

    /** Lets the app retry card payment for an unpaid order. */
    public function pay(Request $request, Order $order, StripeGateway $stripe): JsonResponse
    {
        abort_unless($order->user_id === $request->user()->id, 404);
        abort_if($order->payment_method === 'cod' || $order->payment_status === 'paid' || $order->status === 'cancelled', 422, 'لا يمكن الدفع لهذا الطلب');

        if ($order->payment_method === 'card') {
            return response()->json(['client_secret' => $stripe->clientSecretFor($order), 'publishable_key' => config('shop.stripe.publishable')]);
        }

        return response()->json($this->paymentInstructions($order, $stripe));
    }

    /** Lets the customer switch an unpaid order to another payment method (e.g. Tabby declined -> cash). */
    public function changePayment(Request $request, Order $order, StripeGateway $stripe): JsonResponse
    {
        abort_unless($order->user_id === $request->user()->id, 404);
        abort_if($order->payment_status === 'paid' || $order->status === 'cancelled', 422, 'لا يمكن تغيير طريقة الدفع');
        $method = $request->validate(['payment_method' => ['required', Rule::in(['cod', 'card', 'tabby', 'tamara'])]])['payment_method'];
        abort_if($method === 'cod' && ! $order->branch->accepts_cod, 422, 'الدفع عند الاستلام غير متاح لهذا الفرع.');

        $order->update(['payment_method' => $method, 'payment_status' => 'unpaid', 'payment_reference' => null, 'stripe_payment_intent_id' => null]);

        return response()->json(['order' => $order->fresh()] + $this->paymentInstructions($order, $stripe));
    }

    public function cancel(Request $request, Order $order): JsonResponse
    {
        abort_unless($order->user_id === $request->user()->id, 404);
        abort_unless($order->status === 'pending', 422, 'لا يمكن إلغاء الطلب بعد تأكيده، تواصل معنا.');
        $order->moveTo('cancelled', $request->user(), 'ألغاه العميل');
        $order->slot?->decrement('booked');

        return response()->json($order->fresh());
    }
}
