<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Medicine;

class SearchController extends Controller
{
 public function search(Request $request)
{
    $data = $request->validate([
        'name'   => 'required|string',
        'lat'    => 'nullable|numeric',
        'lng'    => 'nullable|numeric',
    ]);

    $name = $data['name'];
    $lat  = $data['lat'] ?? null;
    $lng  = $data['lng'] ?? null;
    $radius = 500;

    $medicines = Medicine::whereRaw('LOWER(brand_name) LIKE ?', ['%' . strtolower($name) . '%'])->get();

    if ($medicines->isEmpty()) {
        return response()->json(['message' => 'medicine not found'], 404);
    }

    $medicineIds = $medicines->pluck('id')->toArray();

    $query = DB::table('stock_batches')
        ->join('pharmacy_profiles', 'pharmacy_profiles.id', '=', 'stock_batches.pharmacy_id')
        ->join('medicines', 'medicines.id', '=', 'stock_batches.medicine_id')
        ->join('users', 'users.id', '=', 'pharmacy_profiles.user_id')
        ->whereIn('stock_batches.medicine_id', $medicineIds)
        ->where('stock_batches.quantity', '>=', 0)
        ->select(
            'pharmacy_profiles.id',
            'users.name as pharmacy_name',
            'pharmacy_profiles.location as pharmacy_location',
            'pharmacy_profiles.latitude',
            'pharmacy_profiles.longitude',
            'pharmacy_profiles.contact_info',
            'stock_batches.medicine_id',
            'medicines.brand_name as medicine_name',
            'medicines.price',
            'medicines.active_ingredient_id',
            DB::raw('SUM(stock_batches.quantity) as quantity')
        )
        ->groupBy(
            'pharmacy_profiles.id',
            'users.name',
            'pharmacy_profiles.location',
            'pharmacy_profiles.latitude',
            'pharmacy_profiles.longitude',
            'pharmacy_profiles.contact_info',
            'stock_batches.medicine_id',
            'medicines.brand_name',
            'medicines.price',
            'medicines.active_ingredient_id'
        );

    if ($lat !== null && $lng !== null) {
        // Only calculate distance if location is provided
        $haversine = "(6371 * acos(
            cos(radians(?)) 
            * cos(radians(pharmacy_profiles.latitude)) 
            * cos(radians(pharmacy_profiles.longitude) - radians(?)) 
            + sin(radians(?)) 
            * sin(radians(pharmacy_profiles.latitude))
        ))";

        $query->selectRaw("{$haversine} AS distance", [$lat, $lng, $lat])
              ->having('distance', '<=', $radius)
              ->orderBy('distance', 'asc');
    } else {
        $query->selectRaw("NULL as distance");
    }

    $perPage = $request->input('per_page', 5);

    $results = $query->paginate($perPage);

    if ($results->isEmpty()) {
        return response()->json(['message' => 'No pharmacies has that medicine.'], 404);
    }

    // Group results by pharmacy_id
    $grouped = [];
    foreach ($results as $row) {
        $pharmacyId = $row->id;
        if (!isset($grouped[$pharmacyId])) {
            $grouped[$pharmacyId] = [
                'pharmacy_id'      => $pharmacyId,
                'pharmacy_name'    => $row->pharmacy_name,
                'pharmacy_location'=> $row->pharmacy_location,
                'latitude'         => (float) $row->latitude,
                'longitude'        => (float) $row->longitude,
                'contact_info'     => $row->contact_info,
                'distance_km'      => $row->distance !== null ? round((float) $row->distance, 2) : null,
                'medicines'        => [],
            ];
        }
        $grouped[$pharmacyId]['medicines'][] = [
            'medicine_id'           => $row->medicine_id,
            'medicine_name'         => $row->medicine_name,
            'price'                 => $row->price,
            'active_ingredient_id'  => $row->active_ingredient_id,
            'quantity'              => (int) $row->quantity,
        ];
    }

    return response()->json([
        'query' => [
            'name' => $name,
            'lat' => $lat,
            'lng' => $lng,
            'radius_km' => $lat && $lng ? $radius : null,
        ],
        'matches' => array_values($grouped),
        'pagination' => [
            'current_page' => $results->currentPage(),
            'last_page'    => $results->lastPage(),
            'per_page'     => $results->perPage(),
            'total'        => $results->total(),
        ]
    ]);
}


}
