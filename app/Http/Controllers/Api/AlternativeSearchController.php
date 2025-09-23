<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Medicine;

class AlternativeSearchController extends Controller
{
    protected $drugInteraction;

    public function __construct(\App\Http\Controllers\DrugInteractionController $drugInteraction)
    {
        $this->drugInteraction = $drugInteraction;
    }

    public function search(Request $request)
    {
        $data = $request->validate([
            'name'   => 'required|string',
            'lat'    => 'nullable|numeric',
            'lng'    => 'nullable|numeric',
            'user_drugs' => 'array'
        ]);

        $medicine   = $data['name'];
        $lat        = isset($data['lat']) ? (float) $data['lat'] : null;
        $lng        = isset($data['lng']) ? (float) $data['lng'] : null;
        $userDrugs  = $data['user_drugs'] ?? [];
        $radius     = 50; // km

        $results = collect();
        $unavailable = [];

        // 1. Fetch AI alternatives
        $aiAlternatives = $this->fetchAlternativesFromApi($medicine, $userDrugs);

        foreach ($aiAlternatives as $alt) {
            if (!is_string($alt) || empty($alt)) continue;

            $dbMedicine = Medicine::whereRaw('LOWER(brand_name) = ?', [strtolower($alt)])->first();

            if ($dbMedicine) {
                $query = DB::table('stock_batches')
                    ->join('pharmacy_profiles', 'pharmacy_profiles.id', '=', 'stock_batches.pharmacy_id')
                    ->join('medicines', 'medicines.id', '=', 'stock_batches.medicine_id')
                    ->where('stock_batches.medicine_id', $dbMedicine->id)
                    ->where('stock_batches.quantity', '>=', 0)
                    ->select(
                        'pharmacy_profiles.id',
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
                        'pharmacy_profiles.location',
                        'pharmacy_profiles.latitude',
                        'pharmacy_profiles.longitude',
                        'pharmacy_profiles.contact_info',
                        'stock_batches.medicine_id',
                        'medicines.brand_name',
                        'medicines.price',
                        'medicines.active_ingredient_id'
                    );

                // Only calculate distance if lat/lng is provided
                if ($lat !== null && $lng !== null) {
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
                }

                $rows = $query->limit(10)->get();

                if ($rows->isNotEmpty()) {
                    $results = $results->merge($rows);
                }
            } else {
                $unavailable[] = [
                    'name' => $alt,
                    'description' => "AI suggested alternative",
                ];
            }
        }

        if ($results->isEmpty() && empty($unavailable)) {
            return response()->json(['message' => 'No alternatives found'], 404);
        }

        // Transform results
        $payload = $results->map(function ($row) use ($lat, $lng) {
            $result = [
                'pharmacy_id'   => $row->id,
                'pharmacy_location' => $row->pharmacy_location,
                'latitude'      => (float) $row->latitude,
                'longitude'     => (float) $row->longitude,
                'contact_info'  => $row->contact_info,
                'medicine_id'   => $row->medicine_id,
                'medicine_name' => $row->medicine_name,
                'price'         => $row->price,
                'active_ingredient_id' => $row->active_ingredient_id,
                'quantity'      => (int) $row->quantity,
            ];

            // Include distance if calculated
            if (isset($row->distance)) {
                $result['distance_km'] = round((float) $row->distance, 2);
            }

            return $result;
        });

        return response()->json([
            'query' => [
                'name' => $medicine,
                'user_drugs' => $userDrugs,
                'lat' => $lat,
                'lng' => $lng,
                'radius_km' => $radius,
            ],
            'matches' => $payload,
            'unavailable' => $unavailable
        ]);
    }

    private function fetchAlternativesFromApi($medicine, $userDrugs)
    {
        try {
            $prompt = "The user searched for {$medicine}. "
                . "The user is already taking: " . implode(", ", $userDrugs) . ". "
                . "Suggest the 5 most famous medicine alternatives available in Egypt that can replace {$medicine}. "
                . "Do not include unsafe alternatives due to drug interactions. "
                . "if medecine name is single character or number or un understood ignore it and return []. "
                . "Return the answer strictly as a JSON array of medicine names like: "
                . "[\"Alt1\", \"Alt2\", \"Alt3\", \"Alt4\", \"Alt5\"]. "
                . "If no safe alternatives exist, return [].";

            $response = Http::timeout(15)->withHeaders([
                'Content-Type' => 'application/json',
            ])->post(
                "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=" . env('GEMINI_API_KEY'),
                [
                    'contents' => [
                        [
                            'role' => 'user',
                            'parts' => [
                                ['text' => $prompt]
                            ]
                        ]
                    ]
                ]
            );

            if ($response->failed()) {
                Log::error('Gemini API failed', ['response' => $response->body()]);
                return [];
            }

            $data = $response->json();
            $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

            $text = preg_replace('/```json|```/i', '', $text);

            $alternatives = json_decode(trim($text), true);

            if (!is_array($alternatives)) {
                return [];
            }

            return array_values(array_filter(array_map('trim', $alternatives)));
        } catch (\Exception $e) {
            Log::error('Error fetching alternatives', ['error' => $e->getMessage()]);
            return [];
        }
    }
}
