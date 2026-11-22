<?php

namespace App\Services;

use App\Models\Address;
use App\Models\Branch;
use App\Models\DeliverySlot;
use App\Models\Option;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderService
{
    /**
     * Prices are always computed on the server from the database; the app only sends ids and quantities.
     *
     * @param  array{branch_id:int,address_id:int,delivery_slot_id?:int|null,purpose?:string,beneficiary_name?:string|null,
     *               donate_to_charity?:bool,payment_method:string,notes?:string|null,
     *               items:array<int,array{product_id:int,quantity:int,option_ids?:array<int,int>}>}  $data
     */
    public function place(User $user, array $data): Order
    {
        return DB::transaction(function () use ($user, $data) {
            $branch = Branch::where('active', true)->whereHas('vendor', fn ($q) => $q->where('active', true))
                ->find($data['branch_id']) ?? $this->fail('branch_id', 'هذا الفرع غير متاح حالياً.');

            $address = Address::where('user_id', $user->id)->find($data['address_id'])
                ?? $this->fail('address_id', 'العنوان غير موجود.');

            $coverage = $branch->areas()->where('areas.id', $address->area_id)->first()
                ?? $this->fail('address_id', 'هذا الفرع لا يوصل إلى منطقتك.');

            if ($data['payment_method'] === 'cod' && ! $branch->accepts_cod) {
                $this->fail('payment_method', 'الدفع عند الاستلام غير متاح لهذا الفرع.');
            }

            $slot = null;
            if (! empty($data['delivery_slot_id'])) {
                $slot = DeliverySlot::where('branch_id', $branch->id)->lockForUpdate()->find($data['delivery_slot_id'])
                    ?? $this->fail('delivery_slot_id', 'موعد التوصيل غير صالح.');
                if ($slot->date->lt(today()) || $slot->isFull()) {
                    $this->fail('delivery_slot_id', 'موعد التوصيل لم يعد متاحاً، اختر موعداً آخر.');
                }
            }

            $lines = [];
            $subtotal = 0.0;
            foreach ($data['items'] as $i => $item) {
                $product = Product::with('optionGroups', 'variants')->where('branch_id', $branch->id)->where('active', true)
                    ->find($item['product_id']) ?? $this->fail("items.$i.product_id", 'المنتج غير متاح في هذا الفرع.');
                if (! $product->in_stock) {
                    $this->fail("items.$i.product_id", "نفدت الكمية: {$product->name_ar}");
                }

                $variant = null;
                if ($product->variants->isNotEmpty()) {
                    $variant = $product->variants->firstWhere('id', $item['variant_id'] ?? null)
                        ?? $this->fail("items.$i.variant_id", "يرجى اختيار الوزن لـ {$product->name_ar}.");
                    if (! $variant->in_stock) {
                        $this->fail("items.$i.variant_id", "هذا الوزن غير متوفر حالياً: {$variant->label_ar}");
                    }
                }

                [$options, $extra] = $this->resolveOptions($product, $item['option_ids'] ?? [], "items.$i.option_ids");
                $unit = round((float) ($variant?->price ?? $product->price) + $extra, 2);
                $qty = (int) $item['quantity'];
                $lines[] = [
                    'product_id' => $product->id,
                    'product_variant_id' => $variant?->id,
                    'name_ar' => $product->name_ar,
                    'name_en' => $product->name_en,
                    'variant_ar' => $variant?->label_ar,
                    'variant_en' => $variant?->label_en,
                    'quantity' => $qty,
                    'unit_price' => $unit,
                    'options' => $options,
                    'line_total' => round($unit * $qty, 2),
                ];
                $subtotal += $unit * $qty;
            }

            $subtotal = round($subtotal, 2);
            if ($subtotal < (float) $coverage->pivot->min_order) {
                $this->fail('items', "الحد الأدنى للطلب في منطقتك {$coverage->pivot->min_order} درهم.");
            }
            $deliveryFee = (float) $coverage->pivot->delivery_fee;
            $vat = round(($subtotal + $deliveryFee) * config('shop.vat_rate') / 100, 2);

            $order = Order::create([
                'number' => 'tmp-'.Str::uuid(),
                'user_id' => $user->id,
                'branch_id' => $branch->id,
                'address_id' => $address->id,
                'delivery_slot_id' => $slot?->id,
                'purpose' => $data['purpose'] ?? 'normal',
                'beneficiary_name' => $data['beneficiary_name'] ?? null,
                'donate_to_charity' => (bool) ($data['donate_to_charity'] ?? false),
                'status' => 'pending',
                'payment_method' => $data['payment_method'],
                'payment_status' => 'unpaid',
                'subtotal' => $subtotal,
                'delivery_fee' => $deliveryFee,
                'vat' => $vat,
                'total' => round($subtotal + $deliveryFee + $vat, 2),
                'notes' => $data['notes'] ?? null,
            ]);
            // Short, easy-to-say order numbers: 100001, 100002, ...
            $order->update(['number' => (string) (100000 + $order->id)]);
            $order->items()->createMany($lines);
            $order->statusLogs()->create(['status' => 'pending', 'user_id' => $user->id]);
            $slot?->increment('booked');

            return $order->load('items');
        });
    }

    /** @return array{0: array<int, array<string, mixed>>, 1: float} */
    private function resolveOptions(Product $product, array $optionIds, string $field): array
    {
        $groupIds = $product->optionGroups->pluck('id');
        $options = Option::with('group')->where('active', true)->whereIn('id', $optionIds)->get();

        if ($options->count() !== count(array_unique($optionIds)) || $options->contains(fn ($o) => ! $groupIds->contains($o->option_group_id))) {
            $this->fail($field, "خيار غير صالح للمنتج {$product->name_ar}.");
        }
        foreach ($product->optionGroups as $group) {
            $chosen = $options->where('option_group_id', $group->id)->count();
            if ($group->required && $chosen === 0) {
                $this->fail($field, "يرجى اختيار {$group->name_ar}.");
            }
            if (! $group->multiple && $chosen > 1) {
                $this->fail($field, "اختر خياراً واحداً فقط من {$group->name_ar}.");
            }
        }

        $snapshot = $options->map(fn (Option $o) => [
            'option_id' => $o->id,
            'group_ar' => $o->group->name_ar,
            'group_en' => $o->group->name_en,
            'name_ar' => $o->name_ar,
            'name_en' => $o->name_en,
            'extra_price' => (float) $o->extra_price,
        ])->values()->all();

        return [$snapshot, (float) $options->sum('extra_price')];
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
