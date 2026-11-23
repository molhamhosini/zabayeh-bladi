<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Payments\Bnpl;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class BnplController extends Controller
{
    /**
     * Where Tabby/Tamara send the customer back. The redirect itself proves nothing,
     * so we ask the provider for the real status before marking anything paid.
     */
    public function return(Request $request, string $gateway, Order $order): Response
    {
        abort_unless($order->payment_method === $gateway, 404);
        $paid = $request->query('status') === 'success' && Bnpl::for($gateway)->confirm($order);

        return response()->view('payments.return', ['paid' => $paid, 'order' => $order, 'status' => $request->query('status')]);
    }

    /** Server-to-server notifications, used when the customer closes the page before returning. */
    public function webhook(Request $request, string $gateway): Response
    {
        if ($gateway === 'tamara') {
            $token = config('shop.tamara.notification_token');
            $given = $request->query('tamaraToken') ?? $request->bearerToken();
            if (blank($token) || ! hash_equals((string) $token, (string) $given)) {
                return response('unauthorised', 401);
            }
            $ref = $request->input('order_id');
        } else {
            $ref = $request->input('id');
        }

        $order = Order::where('payment_method', $gateway)->where('payment_reference', $ref)->first();
        if ($order) {
            try {
                // confirm() re-reads the status from the provider with our secret key, so a forged body can't mark an order paid.
                Bnpl::for($gateway)->confirm($order);
            } catch (\Throwable $e) {
                Log::warning("BNPL webhook {$gateway} failed: ".$e->getMessage());
            }
        }

        return response('ok');
    }
}
