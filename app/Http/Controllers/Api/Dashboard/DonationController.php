<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\DonationUpdateRequest;
use App\Http\Resources\Dashboard\DonationResource;
use App\Models\Donation;
use App\Models\Medicine;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DonationController extends Controller
{
    use ApiResponse;
    /**
     * Get paginated list of donations with advanced joins and filtering
     */
    public function index(Request $request)
    {
        try {
            $query = Donation::select([
                'donations.id',
                'donations.user_id',
                'donations.location',
                'donations.contact_info',
                'donations.verified',
                'donations.status',
                'donations.sealed_confirmed',
                'donations.created_at',
                'donations.updated_at',
                'users.name as donor_name'
            ])
            ->join('users', 'donations.user_id', '=', 'users.id')
            ->where('users.status', 'active')
            ->with(['medicines' => function ($query) {
                $query->select('medicines.id', 'medicines.brand_name', 'donation_medicines.quantity', 'donation_medicines.expiry_date', 'donation_medicines.batch_num');
            }])
            ->orderBy('donations.created_at', 'desc');

            // Apply verified filter
            if ($request->has('verified') && $request->verified !== '') {
                $query->where('donations.verified', $request->boolean('verified'));
            }

            // Apply user_id filter
            if ($request->has('user_id') && !empty($request->user_id)) {
                $query->where('donations.user_id', $request->user_id);
            }

            // Use paginateData helper for consistent pagination
            $paginatedData = paginateData($query, 10, null, []);

            // Transform data using DonationResource
            $transformedData = collect($paginatedData['data'])->map(function ($donation) {
                // Create a mock Donation object for the resource
                $mockDonation = new Donation([
                    'user_id' => $donation->user_id,
                    'location' => $donation->location,
                    'contact_info' => $donation->contact_info,
                    'verified' => $donation->verified,
                    'status' => $donation->status,
                    'sealed_confirmed' => $donation->sealed_confirmed,
                    'created_at' => $donation->created_at,
                    'updated_at' => $donation->updated_at,
                ]);

                // Set the ID and timestamps manually since they're not fillable
                $mockDonation->id = $donation->id;
                $mockDonation->created_at = $donation->created_at;
                $mockDonation->updated_at = $donation->updated_at;

                // Add donor name to the mock object
                $mockDonation->donor_name = $donation->donor_name;

                return new DonationResource($mockDonation);
            });

            // Update the data in the paginated response
            $paginatedData['data'] = $transformedData;

            return $this->success($paginatedData, 'Donations retrieved successfully');

        } catch (\Exception $e) {
            return $this->serverError('Failed to retrieve donations: ' . $e->getMessage());
        }
    }

    /**
     * Get donations for the authenticated user
     */
    public function myDonations(Request $request)
    {
        $perPage = $request->input('per_page', 10);
        $searchTerm = $request->input('search');

        $query = Donation::where('user_id', $request->user()->id)
            ->with(['medicines'])
            ->orderBy('created_at', 'desc');

        $searchFields = ['location', 'contact_info'];

        $result = paginateData($query, $perPage, $searchTerm, $searchFields);

        // Transform the data using the resource
        $result['data'] = DonationResource::collection($result['data']);

        return $this->success($result, 'Your donations retrieved successfully');
    }

    /**
     * Get verified donations only
     */
    public function verified(Request $request)
    {
        $perPage = $request->input('per_page', 10);
        $searchTerm = $request->input('search');

        $query = Donation::where('verified', true)
            ->with(['user', 'medicines'])
            ->orderBy('created_at', 'desc');

        $searchFields = ['location', 'contact_info'];

        $result = paginateData($query, $perPage, $searchTerm, $searchFields);

        // Transform the data using the resource
        $result['data'] = DonationResource::collection($result['data']);

        return $this->success($result, 'Verified donations retrieved successfully');
    }

    /**
     * Update the specified donation
     *
     * @param DonationUpdateRequest $request
     * @param Donation $donation
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(DonationUpdateRequest $request, Donation $donation)
    {
        try {
            // Validate the request manually to ensure it works properly
            $validator = Validator::make($request->all(), [
                'verified' => 'nullable|boolean',
                'status' => 'nullable|in:proposed,under_review,approved,rejected,collected',
                'location' => 'nullable|string',
                'contact_info' => 'nullable|string',
                'medicine_id' => 'nullable|exists:medicines,id',
                'quantity' => 'required_with:medicine_id|integer|min:1',
                'expiry_date' => 'nullable|date|after:today',
                'batch_num' => 'nullable|string|max:100',
            ], [
                'status.in' => 'The selected status is invalid. Valid values are: proposed, under_review, approved, rejected, collected.',
            ]);

            if ($validator->fails()) {
                return $this->error('Validation failed', 422, $validator->errors());
            }

            $validatedData = $validator->validated();

            // Use database transaction to ensure data consistency
            $updatedDonation = DB::transaction(function () use ($donation, $validatedData) {
                // Update donation basic info
                $updateData = [];

                if (isset($validatedData['verified'])) {
                    $updateData['verified'] = $validatedData['verified'];
                }

                if (isset($validatedData['status'])) {
                    $updateData['status'] = $validatedData['status'];
                }

                if (isset($validatedData['location'])) {
                    $updateData['location'] = $validatedData['location'];
                }

                if (isset($validatedData['contact_info'])) {
                    $updateData['contact_info'] = $validatedData['contact_info'];
                }

                if (!empty($updateData)) {
                    $donation->update($updateData);
                }

                // Handle medicine addition if provided
                if (isset($validatedData['medicine_id']) && $validatedData['medicine_id']) {
                    $medicine = Medicine::find($validatedData['medicine_id']);

                    if ($medicine) {
                        // Simulate AI validation for expiry_date and quantity
                        $validationResult = $this->simulateAiValidation(
                            $validatedData['expiry_date'] ?? null,
                            $validatedData['quantity'] ?? 0
                        );

                        if ($validationResult['valid']) {
                            // Add medicine to donation
                            $donation->medicines()->attach($medicine->id, [
                                'quantity' => $validatedData['quantity'],
                                'expiry_date' => $validatedData['expiry_date'],
                                'batch_num' => $validatedData['batch_num'] ?? null,
                            ]);
                        } else {
                            // Reject donation if validation fails
                            $donation->update(['verified' => false]);
                            throw new \Exception($validationResult['message']);
                        }
                    }
                }

                return $donation->fresh();
            });

            // Load the donation with its relationships
            $updatedDonation->load(['user', 'medicines']);

            // Transform the response using DonationResource
            $donationResource = new DonationResource($updatedDonation);

            return $this->success($donationResource, 'Donation updated successfully');

        } catch (\Exception $e) {
            return $this->serverError('Failed to update donation: ' . $e->getMessage());
        }
    }

    /**
     * Soft delete the specified donation
     *
     * @param Donation $donation
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Donation $donation)
    {
        try {
            // Check if donation is already soft deleted
            if ($donation->trashed()) {
                return $this->error('Donation is already deleted', 404);
            }

            // Soft delete the donation
            $donation->delete();

            return $this->success(null, 'Donation deleted successfully');

        } catch (\Exception $e) {
            return $this->serverError('Failed to delete donation: ' . $e->getMessage());
        }
    }

    /**
     * Restore a soft-deleted donation
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function restore($id)
    {
        try {
            // Find the donation including soft-deleted ones
            $donation = Donation::withTrashed()->find($id);

            if (!$donation) {
                return $this->error('Donation not found', 404);
            }

            // Check if donation is actually soft deleted
            if (!$donation->trashed()) {
                return $this->error('Donation is not deleted', 400);
            }

            // Restore the donation
            $donation->restore();

            // Load the donation with its relationships
            $donation->load(['user', 'medicines']);

            // Transform the response using DonationResource
            $donationResource = new DonationResource($donation);

            return $this->success($donationResource, 'Donation restored successfully');

        } catch (\Exception $e) {
            return $this->serverError('Failed to restore donation: ' . $e->getMessage());
        }
    }

    /**
     * Get donation statistics
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function stats()
    {
        try {
            // Total donated quantity (sum from donation_medicines)
            $totalDonatedQuantity = DB::table('donation_medicines')
                ->sum('quantity');

            // Distribution by location (count per location)
            $distributionByLocation = Donation::selectRaw('location, COUNT(*) as count')
                ->whereNotNull('location')
                ->groupBy('location')
                ->pluck('count', 'location')
                ->toArray();

            // Additional statistics
            $totalDonations = Donation::count();
            $verifiedDonations = Donation::where('verified', true)->count();
            $pendingDonations = Donation::where('verified', false)->count();

            // Prepare statistics data
            $stats = [
                'total_donated_quantity' => $totalDonatedQuantity,
                'distribution_by_location' => $distributionByLocation,
                'total_donations' => $totalDonations,
                'verified_donations' => $verifiedDonations,
                'pending_donations' => $pendingDonations,
                'verification_rate' => $totalDonations > 0 ? round(($verifiedDonations / $totalDonations) * 100, 2) : 0,
            ];

            return $this->success($stats, 'Donation statistics retrieved successfully');

        } catch (\Exception $e) {
            return $this->serverError('Failed to retrieve donation statistics: ' . $e->getMessage());
        }
    }

    /**
     * Get a specific donation by ID
     */
    public function show(Donation $donation)
    {
        $donation->load(['user', 'medicines']);

        return $this->success(['donation' => new DonationResource($donation)], 'Donation retrieved successfully');
    }

    /**
     * Simulate AI validation for expiry_date and quantity
     *
     * @param string|null $expiryDate
     * @param int $quantity
     * @return array
     */
    private function simulateAiValidation(?string $expiryDate, int $quantity): array
    {
        $validationResult = [
            'valid' => true,
            'message' => 'Validation passed',
            'checks' => []
        ];

        // Check quantity
        if ($quantity <= 0) {
            $validationResult['valid'] = false;
            $validationResult['checks'][] = 'Quantity must be greater than 0';
        }

        // Check expiry date
        if ($expiryDate) {
            $expiry = \Carbon\Carbon::parse($expiryDate);
            $now = \Carbon\Carbon::now();

            if ($expiry->isPast()) {
                $validationResult['valid'] = false;
                $validationResult['checks'][] = 'Medicine has expired';
            } elseif ($expiry->diffInDays($now) < 30) {
                $validationResult['checks'][] = 'Medicine expires within 30 days';
            }
        }

        // Simulate additional AI checks
        if ($quantity > 100) {
            $validationResult['checks'][] = 'Large quantity donation - manual review recommended';
        }

        if (!$validationResult['valid']) {
            $validationResult['message'] = 'Validation failed: ' . implode(', ', $validationResult['checks']);
        } else {
            $validationResult['message'] = 'Validation passed: ' . implode(', ', $validationResult['checks']);
        }

        return $validationResult;
    }
}
