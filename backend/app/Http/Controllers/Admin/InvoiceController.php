<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Support\InvoiceCompany;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Printable tax invoice for an order: A4 (default) or 80mm thermal receipt (?size=80mm). */
class InvoiceController extends Controller
{
    public function show(Request $request, Order $order): View
    {
        $user = $request->user();
        abort_unless(
            $user && ($user->role === User::ROLE_ADMIN || ($user->role === User::ROLE_BRANCH_STAFF && $user->branch_id === $order->branch_id)),
            403
        );

        $order->load('items', 'user', 'branch.vendor', 'branch.emirate', 'address.area.emirate', 'slot');
        $taxable = round((float) $order->subtotal + (float) $order->delivery_fee, 2);

        return view('invoices.show', [
            'order' => $order,
            'company' => InvoiceCompany::get(),
            'taxable' => $taxable,
            'rate' => 5,
            'thermal' => $request->query('size') === '80mm',
            'autoprint' => $request->boolean('print'),
        ]);
    }
}
