<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
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
            'payment_method' => ['required', Rule::in(['cod', 'card'])],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:20'],
            'items.*.option_ids' => ['array'],
            'items.*.option_ids.*' => ['integer'],
        ]);

        $order = $orders->place($request->user(), $data);
        $response = ['order' => $order];
        if ($order->payment_method === 'card') {
            $response['stripe'] = ['client_secret' => $stripe->clientSecretFor($order), 'publishable_key' => config('shop.stripe.publishable')];
        }

        return response()->json($response, 201);
    }

    /** Lets the app retry card payment for an unpaid order. */
    public function pay(Request $request, Order $order, StripeGateway $stripe): JsonResponse
    {
        abort_unless($order->user_id === $request->user()->id, 404);
        abort_if($order->payment_method !== 'card' || $order->payment_status === 'paid' || $order->status === 'cancelled', 422, 'لا يمكن الدفع لهذا الطلب');

        return response()->json(['client_secret' => $stripe->clientSecretFor($order), 'publishable_key' => config('shop.stripe.publishable')]);
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
