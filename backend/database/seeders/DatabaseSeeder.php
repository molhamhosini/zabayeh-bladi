<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Branch;
use App\Models\Category;
use App\Models\DeliverySlot;
use App\Models\Emirate;
use App\Models\OptionGroup;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $emirates = [
            ['abu-dhabi', 'أبوظبي', 'Abu Dhabi', [
                ['أبوظبي - وسط المدينة', 'Abu Dhabi City', 24.4539, 54.3773], ['خليفة سيتي', 'Khalifa City', 24.4194, 54.5781],
                ['مدينة محمد بن زايد', 'Mohammed Bin Zayed City', 24.3486, 54.5531], ['جزيرة ياس', 'Yas Island', 24.4959, 54.6086],
                ['الشامخة', 'Al Shamkha', 24.3920, 54.7020], ['العين', 'Al Ain', 24.2075, 55.7447],
            ]],
            ['dubai', 'دبي', 'Dubai', [
                ['ديرة', 'Deira', 25.2697, 55.3095], ['بر دبي', 'Bur Dubai', 25.2532, 55.2972], ['الورقاء', 'Al Warqa', 25.1915, 55.4156],
                ['مردف', 'Mirdif', 25.2190, 55.4206], ['البرشاء', 'Al Barsha', 25.1124, 55.1990], ['جميرا', 'Jumeirah', 25.2048, 55.2416],
                ['دبي مارينا', 'Dubai Marina', 25.0805, 55.1403], ['الخوانيج', 'Al Khawaneej', 25.2276, 55.4942],
            ]],
            ['sharjah', 'الشارقة', 'Sharjah', [
                ['الشارقة - وسط المدينة', 'Sharjah City', 25.3463, 55.4209], ['النهدة', 'Al Nahda', 25.3011, 55.3735],
                ['مويلح', 'Muwaileh', 25.2946, 55.4612], ['الذيد', 'Al Dhaid', 25.2881, 55.8817],
            ]],
            ['ajman', 'عجمان', 'Ajman', [['عجمان - وسط المدينة', 'Ajman City', 25.4052, 55.5136], ['الجرف', 'Al Jurf', 25.4007, 55.4950]]],
            ['umm-al-quwain', 'أم القيوين', 'Umm Al Quwain', [['أم القيوين', 'Umm Al Quwain City', 25.5647, 55.5552]]],
            ['ras-al-khaimah', 'رأس الخيمة', 'Ras Al Khaimah', [['رأس الخيمة - وسط المدينة', 'Ras Al Khaimah City', 25.7895, 55.9432], ['الحمرا', 'Al Hamra', 25.6930, 55.7780]]],
        ];
        foreach ($emirates as $i => [$slug, $ar, $en, $areas]) {
            $emirate = Emirate::create(['slug' => $slug, 'name_ar' => $ar, 'name_en' => $en, 'sort' => $i]);
            foreach ($areas as [$aar, $aen, $lat, $lng]) {
                $emirate->areas()->create(['name_ar' => $aar, 'name_en' => $aen, 'lat' => $lat, 'lng' => $lng]);
            }
        }

        $categories = collect([
            ['naimi', 'نعيمي', 'Naimi'], ['harri', 'حري', 'Harri'], ['najdi', 'نجدي', 'Najdi'],
            ['goat', 'ماعز', 'Goat'], ['veal', 'عجل', 'Veal'], ['camel', 'حاشي', 'Camel'],
        ])->mapWithKeys(fn ($c, $i) => [$c[0] => Category::create(['slug' => $c[0], 'name_ar' => $c[1], 'name_en' => $c[2], 'sort' => $i])]);

        $groups = [
            ['التقطيع', 'Cutting', true, false, [['أرباع', 'Quarters', 0], ['تقطيع ثلاجة', 'Fridge cut', 0], ['تقطيع مندي', 'Mandi cut', 0], ['تقطيع كبسة', 'Kabsa cut', 0], ['كامل بدون تقطيع', 'Whole, uncut', 0]]],
            ['التغليف', 'Packaging', true, false, [['أكياس عادية', 'Standard bags', 0], ['أطباق مغلفة', 'Sealed trays', 15], ['تغليف مفرغ (فاكيوم)', 'Vacuum packed', 30]]],
            ['الرأس', 'Head', false, false, [['مع الرأس', 'With head', 0], ['بدون رأس', 'Without head', 0]]],
            ['الكرشة والمعلاق', 'Offal', false, true, [['مع الكرشة', 'With tripe', 0], ['مع المعلاق', 'With liver & heart', 0]]],
        ];
        $groupIds = [];
        foreach ($groups as $i => [$ar, $en, $req, $multi, $opts]) {
            $g = OptionGroup::create(['name_ar' => $ar, 'name_en' => $en, 'required' => $req, 'multiple' => $multi, 'sort' => $i]);
            foreach ($opts as $j => [$oar, $oen, $price]) {
                $g->options()->create(['name_ar' => $oar, 'name_en' => $oen, 'extra_price' => $price, 'sort' => $j]);
            }
            $groupIds[] = $g->id;
        }

        $own = Vendor::create(['name_ar' => 'ذبائح بلادي', 'name_en' => 'Zabayeh Bladi', 'is_own' => true]);
        $dubai = Emirate::where('slug', 'dubai')->first();
        $branch = Branch::create(['vendor_id' => $own->id, 'emirate_id' => $dubai->id, 'name_ar' => 'ذبائح بلادي - دبي', 'name_en' => 'Zabayeh Bladi - Dubai', 'lat' => 25.2190, 'lng' => 55.4206]);
        $branch->areas()->attach(Area::whereHas('emirate', fn ($q) => $q->whereIn('slug', ['dubai', 'sharjah', 'ajman']))->pluck('id')
            ->mapWithKeys(fn ($id) => [$id => ['delivery_fee' => 0, 'min_order' => 0]]));

        $catalog = [
            ['naimi', 'نعيمي صغير', 'Small Naimi', 12, 15, 950], ['naimi', 'نعيمي وسط', 'Medium Naimi', 16, 19, 1150], ['naimi', 'نعيمي كبير', 'Large Naimi', 20, 24, 1400],
            ['harri', 'حري صغير', 'Small Harri', 11, 14, 850], ['harri', 'حري وسط', 'Medium Harri', 15, 18, 1050],
            ['goat', 'تيس محلي', 'Local goat', 10, 13, 800], ['veal', 'عجل محلي', 'Local veal', 80, 110, 4500],
        ];
        foreach ($catalog as $i => [$cat, $ar, $en, $min, $max, $price]) {
            $p = Product::create(['branch_id' => $branch->id, 'category_id' => $categories[$cat]->id, 'name_ar' => $ar, 'name_en' => $en,
                'description_ar' => "وزن تقريبي من {$min} إلى {$max} كغ بعد الذبح، يُذبح ويُجهّز يوم التوصيل.",
                'description_en' => "Approx. {$min}-{$max} kg after slaughter, prepared on delivery day.",
                'weight_min_kg' => $min, 'weight_max_kg' => $max, 'price' => $price, 'sort' => $i]);
            $p->optionGroups()->attach($groupIds);
        }

        for ($d = 1; $d <= 14; $d++) {
            foreach ([['09:00', '13:00'], ['14:00', '18:00'], ['18:00', '22:00']] as [$s, $e]) {
                DeliverySlot::create(['branch_id' => $branch->id, 'date' => today()->addDays($d), 'starts_at' => $s, 'ends_at' => $e, 'capacity' => 15]);
            }
        }

        User::create(['name' => 'Admin', 'email' => env('ADMIN_EMAIL') ?: 'admin@zabayehbladi.test', 'password' => env('ADMIN_PASSWORD') ?: 'password', 'role' => User::ROLE_ADMIN]);
        User::create(['name' => 'Dubai branch', 'email' => 'dubai@zabayehbladi.test', 'password' => 'password', 'role' => User::ROLE_BRANCH_STAFF, 'branch_id' => $branch->id]);
    }
}
