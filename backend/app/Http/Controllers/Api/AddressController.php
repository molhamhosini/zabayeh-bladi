<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    private function rules(): array
    {
        return [
            'area_id' => ['required', 'integer', 'exists:areas,id'],
            'label' => ['nullable', 'string', 'max:50'],
            'details' => ['required', 'string', 'max:500'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json($request->user()->addresses()->with('area.emirate')->latest()->get());
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json($request->user()->addresses()->create($request->validate($this->rules()))->load('area.emirate'), 201);
    }

    public function update(Request $request, Address $address): JsonResponse
    {
        abort_unless($address->user_id === $request->user()->id, 404);
        $address->update($request->validate($this->rules()));

        return response()->json($address->load('area.emirate'));
    }

    public function destroy(Request $request, Address $address): JsonResponse
    {
        abort_unless($address->user_id === $request->user()->id, 404);
        $address->delete();

        return response()->json(null, 204);
    }
}
