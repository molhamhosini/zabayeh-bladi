<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Banner;
use App\Models\Branch;
use App\Models\Category;
use App\Models\DeliverySlot;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CatalogController extends Controller
{
    public function categories(): JsonResponse
    {
        return response()->json(Category::where('active', true)->orderBy('sort')->get(['id', 'slug', 'name_ar', 'name_en', 'image']));
    }

    /** Everything the home screen needs in one call: banners, sections and the offers row for the area. */
    public function home(Request $request): JsonResponse
    {
        $areaId = (int) $request->validate(['area_id' => ['required', 'integer', 'exists:areas,id']])['area_id'];
        $with = ['branch:id,vendor_id,name_ar,name_en', 'branch.vendor:id,name_ar,name_en,logo,is_own', 'category:id,slug,name_ar,name_en'];

        return response()->json([
            'payment_methods' => array_values(array_filter([
                'card' => filled(config('shop.stripe.secret')) ? 'card' : null,
                'tabby' => app(\App\Services\Payments\TabbyGateway::class)->isConfigured() ? 'tabby' : null,
                'tamara' => app(\App\Services\Payments\TamaraGateway::class)->isConfigured() ? 'tamara' : null,
                'cod' => 'cod',
            ])),
            'banners' => Banner::live()->get(['id', 'title_ar', 'title_en', 'subtitle_ar', 'subtitle_en', 'image', 'category_id']),
            'categories' => Category::where('active', true)->orderBy('sort')->get(['id', 'slug', 'name_ar', 'name_en', 'image']),
            'offers' => Product::availableIn($areaId)->where('in_stock', true)
                ->where(fn ($q) => $q->where('is_featured', true)->orWhereColumn('compare_at_price', '>', 'price'))
                ->with($with)->orderBy('sort')->limit(12)->get(),
            'popular' => Product::availableIn($areaId)->where('in_stock', true)->with($with)->orderBy('sort')->limit(12)->get(),
        ]);
    }

    /** Products the user can order in their area, optionally filtered by category or branch. */
    public function products(Request $request): JsonResponse
    {
        $data = $request->validate([
            'area_id' => ['required', 'integer', 'exists:areas,id'],
            'category_id' => ['nullable', 'integer'],
            'branch_id' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:50'],
        ]);

        $products = Product::availableIn((int) $data['area_id'])
            ->when($data['category_id'] ?? null, fn ($q, $id) => $q->where('category_id', $id))
            ->when($data['branch_id'] ?? null, fn ($q, $id) => $q->where('branch_id', $id))
            ->when($data['q'] ?? null, fn ($q, $s) => $q->where(fn ($w) => $w->where('name_ar', 'like', "%$s%")->orWhere('name_en', 'like', "%$s%")))
            ->with(['branch:id,vendor_id,name_ar,name_en', 'branch.vendor:id,name_ar,name_en,logo,is_own', 'category:id,slug,name_ar,name_en'])
            ->orderByDesc('in_stock')->orderBy('sort')->orderBy('price')
            ->paginate(20);

        return response()->json($products);
    }

    public function product(Product $product): JsonResponse
    {
        abort_unless($product->active, 404);

        return response()->json($product->load([
            'branch:id,vendor_id,name_ar,name_en,accepts_cod', 'branch.vendor:id,name_ar,name_en,logo,is_own',
            'category:id,slug,name_ar,name_en',
            'optionGroups.options' => fn ($q) => $q->where('active', true),
            'variants',
        ]));
    }

    /** Branches delivering to the area, with their fee there. */
    public function branches(Request $request): JsonResponse
    {
        $areaId = (int) $request->validate(['area_id' => ['required', 'integer', 'exists:areas,id']])['area_id'];

        return response()->json(Branch::where('active', true)
            ->whereHas('vendor', fn ($q) => $q->where('active', true))
            ->whereHas('areas', fn ($q) => $q->where('areas.id', $areaId))
            ->with(['vendor:id,name_ar,name_en,logo,is_own', 'areas' => fn ($q) => $q->where('areas.id', $areaId)])
            ->get()
            ->map(fn (Branch $b) => [
                'id' => $b->id, 'name_ar' => $b->name_ar, 'name_en' => $b->name_en, 'accepts_cod' => $b->accepts_cod,
                'vendor' => $b->vendor,
                'delivery_fee' => (float) $b->areas->first()->pivot->delivery_fee,
                'min_order' => (float) $b->areas->first()->pivot->min_order,
            ]));
    }

    public function slots(Request $request, Branch $branch): JsonResponse
    {
        // Keep two weeks of slots open from each emirate's delivery windows: synced once a day per branch,
        // and again whenever a window is edited (no cron needed).
        $version = Cache::get('delivery-windows-version', '0');
        Cache::remember("slots-synced:{$branch->id}:".today()->toDateString().":{$version}", now()->endOfDay(),
            fn () => DeliverySlot::generateFor($branch->id, 15) + 1);

        // Slots follow the customer's emirate (taken from their area).
        $emirateId = $request->filled('area_id') ? Area::whereKey($request->integer('area_id'))->value('emirate_id') : null;

        $cutoff = now()->addHours(3); // time needed to slaughter and prepare

        return response()->json(DeliverySlot::where('branch_id', $branch->id)
            ->when($emirateId, fn ($q) => $q->where('emirate_id', $emirateId))
            ->whereBetween('date', [today(), today()->addDays(14)])
            ->whereColumn('booked', '<', 'capacity')
            ->orderBy('date')->orderBy('starts_at')
            ->get()
            ->reject(fn (DeliverySlot $s) => $s->date->copy()->setTimeFromTimeString($s->starts_at)->lt($cutoff))
            ->values()
            ->map(fn (DeliverySlot $s) => $s->only('id', 'starts_at', 'ends_at') + [
                'date' => $s->date->toDateString(), 'label_ar' => $s->label(), 'label_en' => $s->labelEn(),
            ]));
    }
}
