<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Emirate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    /** Emirates with their areas, for the manual location picker. */
    public function emirates(): JsonResponse
    {
        return response()->json(Emirate::where('active', true)->orderBy('sort')
            ->with(['areas' => fn ($q) => $q->where('active', true)->orderBy('name_en')])
            ->get(['id', 'slug', 'name_ar', 'name_en']));
    }

    /** Resolves the user's GPS position to the nearest served area (first screen of the app). */
    public function resolve(Request $request): JsonResponse
    {
        $data = $request->validate(['lat' => ['required', 'numeric', 'between:-90,90'], 'lng' => ['required', 'numeric', 'between:-180,180']]);
        $area = Area::nearest((float) $data['lat'], (float) $data['lng']);

        // Outside the served emirates (e.g. more than ~40 km from any known area) we ask the user to pick manually.
        if (! $area || Area::distanceKm($data['lat'], $data['lng'], $area->lat, $area->lng) > 40) {
            return response()->json(['served' => false, 'message' => 'عذراً، لا نوصل إلى موقعك حالياً.'], 200);
        }

        return response()->json(['served' => true, 'area' => $area->load('emirate:id,slug,name_ar,name_en')]);
    }
}
