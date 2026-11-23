<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Banner;
use App\Models\Branch;
use App\Models\Category;
use App\Models\DeliverySlot;
use App\Models\DeliveryWindow;
use App\Models\Emirate;
use App\Models\OptionGroup;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

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
            ['طريقة التقطيع', 'Cutting', true, false, 'cards', [['تقطيع عربي 8 قطع', 'Arabic cut, 8 pieces', 0, true], ['تقطيع عربي 4 قطع', 'Arabic cut, 4 pieces', 0, false], ['تقطيع ثلاجة', 'Fridge cut', 0, false], ['تقطيع مندي', 'Mandi cut', 0, false], ['تقطيع كبسة', 'Kabsa cut', 0, false], ['كامل بدون تقطيع', 'Whole, uncut', 0, false]]],
            ['طريقة التغليف', 'Packaging', true, false, 'cards', [['كرتون (بدون ثلج)', 'Carton (no ice)', 0, true], ['صندوق فوم مع الثلج', 'Foam box with ice', 20, false], ['أطباق مغلفة', 'Sealed trays', 15, false], ['تغليف مفرغ (فاكيوم)', 'Vacuum packed', 30, false]]],
            ['يشمل الطلب على', 'Order includes', false, true, 'checklist', [['الرأس', 'Head', 0, true], ['المصران', 'Intestines', 0, true], ['الكرش', 'Tripe', 0, true]]],
        ];
        $groupIds = [];
        foreach ($groups as $i => [$ar, $en, $req, $multi, $display, $opts]) {
            $g = OptionGroup::create(['name_ar' => $ar, 'name_en' => $en, 'required' => $req, 'multiple' => $multi, 'display' => $display, 'sort' => $i]);
            foreach ($opts as $j => [$oar, $oen, $price, $default]) {
                $g->options()->create(['name_ar' => $oar, 'name_en' => $oen, 'extra_price' => $price, 'default_selected' => $default, 'sort' => $j]);
            }
            $groupIds[] = $g->id;
        }

        $own = Vendor::create(['name_ar' => 'ذبائح بلادي', 'name_en' => 'Zabayeh Bladi', 'is_own' => true]);
        $dubai = Emirate::where('slug', 'dubai')->first();
        $branch = Branch::create(['vendor_id' => $own->id, 'emirate_id' => $dubai->id, 'name_ar' => 'ذبائح بلادي - دبي', 'name_en' => 'Zabayeh Bladi - Dubai', 'lat' => 25.2190, 'lng' => 55.4206]);
        $branch->areas()->attach(Area::whereHas('emirate', fn ($q) => $q->whereIn('slug', ['dubai', 'sharjah', 'ajman']))->pluck('id')
            ->mapWithKeys(fn ($id) => [$id => ['delivery_fee' => 0, 'min_order' => 0]]));

        // Each animal has weight/age options with their own price.
        $catalog = [
            ['naimi', 'خروف نعيمي', 'Naimi sheep', 'من أجود السلالات المحلية، لحم طري ودهن معتدل.', 'A prized local breed with tender meat.', true, [
                [8, 10, '2-4 شهور', '2-4 months', 1143, 1250], [11, 13, '4-6 شهور', '4-6 months', 1191, null], [14, 16, '6-8 شهور', '6-8 months', 1240, null], [17, 20, '8-12 شهر', '8-12 months', 1390, null]]],
            ['harri', 'خروف حري', 'Harri sheep', 'سلالة حري الشهيرة، مناسبة للمندي والكبسة.', 'The popular Harri breed, ideal for mandi and kabsa.', true, [
                [8, 10, '2-4 شهور', '2-4 months', 899, 990], [11, 13, '4-6 شهور', '4-6 months', 975, null], [14, 16, '6-8 شهور', '6-8 months', 1060, null]]],
            ['najdi', 'خروف نجدي', 'Najdi sheep', 'نجدي أصيل بلحم غني.', 'Authentic Najdi with rich meat.', false, [
                [12, 14, '4-6 شهور', '4-6 months', 1290, null], [15, 18, '6-8 شهور', '6-8 months', 1450, null]]],
            ['goat', 'تيس محلي', 'Local goat', 'تيس محلي طازج.', 'Fresh local goat.', true, [
                [7, 9, '3-5 شهور', '3-5 months', 720, 790], [10, 12, '5-7 شهور', '5-7 months', 850, null]]],
            ['veal', 'عجل محلي', 'Local veal', 'عجل محلي يُذبح حسب الطلب.', 'Local veal slaughtered to order.', false, [
                [80, 100, '', '', 4500, null], [100, 130, '', '', 5600, null]]],
        ];
        // Product photos shipped with the repo (database/seeders/images) are copied to the public disk.
        $photos = [];
        foreach (['naimi' => 'naimi.jpg', 'goat' => 'goat-local.jpg'] as $cat => $file) {
            Storage::disk('public')->put('products/'.$file, file_get_contents(__DIR__.'/images/'.$file));
            $photos[$cat] = 'products/'.$file;
        }
        foreach ($catalog as $i => [$cat, $ar, $en, $dar, $den, $featured, $variants]) {
            $p = Product::create(['branch_id' => $branch->id, 'category_id' => $categories[$cat]->id, 'name_ar' => $ar, 'name_en' => $en,
                'description_ar' => $dar.' يُذبح ويُجهّز يوم التوصيل.', 'description_en' => $den.' Prepared on delivery day.',
                'price' => $variants[0][4], 'is_featured' => $featured, 'sort' => $i]);
            foreach ($variants as $j => [$min, $max, $agear, $ageen, $price, $old]) {
                $p->variants()->create(['weight_min_kg' => $min, 'weight_max_kg' => $max, 'age_ar' => $agear ?: null, 'age_en' => $ageen ?: null,
                    'price' => $price, 'compare_at_price' => $old, 'sort' => $j]);
            }
            if (isset($photos[$cat])) {
                $p->update(['image' => $photos[$cat]]);
            }
            $p->syncFromVariants();
            $p->optionGroups()->attach($cat === 'veal' ? array_slice($groupIds, 0, 2) : $groupIds);
        }

        // Standard delivery windows for every emirate; edit per emirate in the admin (أوقات التوصيل).
        foreach (Emirate::all() as $emirate) {
            foreach (DeliverySlot::DEFAULT_WINDOWS as [$s, $e]) {
                DeliveryWindow::create(['emirate_id' => $emirate->id, 'starts_at' => $s.':00', 'ends_at' => $e.':00', 'capacity' => 15]);
            }
        }
        DeliverySlot::generateFor($branch->id, 15);

        Banner::create(['title_ar' => 'ذبيحتك طازجة في نفس اليوم', 'title_en' => 'Same-day fresh slaughter', 'subtitle_ar' => 'نعيمي وحري ونجدي من مزارع الإمارات', 'subtitle_en' => 'Naimi, Harri & Najdi from UAE farms', 'category_id' => $categories['naimi']->id, 'sort' => 1]);
        Banner::create(['title_ar' => 'العقيقة والنذور', 'title_en' => 'Aqiqah & vows', 'subtitle_ar' => 'نذبح ونوزّع عنك أو نوصلها لبيتك', 'subtitle_en' => 'We slaughter and distribute, or deliver home', 'sort' => 2]);
        Banner::create(['title_ar' => 'توصيل مجاني', 'title_en' => 'Free delivery', 'subtitle_ar' => 'في دبي والشارقة وعجمان', 'subtitle_en' => 'In Dubai, Sharjah & Ajman', 'sort' => 3]);

        if (app()->isProduction()) {
            // Live servers: the admin comes from ADMIN_EMAIL / ADMIN_PASSWORD, and no demo branch login is created.
            if (blank(env('ADMIN_EMAIL')) || strlen((string) env('ADMIN_PASSWORD')) < 12) {
                throw new \RuntimeException('Set ADMIN_EMAIL and a strong ADMIN_PASSWORD (12+ characters) in .env before seeding.');
            }
            User::create(['name' => 'Admin', 'email' => env('ADMIN_EMAIL'), 'password' => env('ADMIN_PASSWORD'), 'role' => User::ROLE_ADMIN]);

            return;
        }

        User::create(['name' => 'Admin', 'email' => env('ADMIN_EMAIL') ?: 'admin@zabayehbladi.test', 'password' => env('ADMIN_PASSWORD') ?: 'password', 'role' => User::ROLE_ADMIN]);
        User::create(['name' => 'Dubai branch', 'email' => 'dubai@zabayehbladi.test', 'password' => 'password', 'role' => User::ROLE_BRANCH_STAFF, 'branch_id' => $branch->id]);
    }
}
