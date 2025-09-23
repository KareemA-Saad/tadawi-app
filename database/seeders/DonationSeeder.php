<?php

namespace Database\Seeders;

use App\Models\Donation;
use App\Models\Medicine;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DonationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::where('role', 'patient')->get();
        $medicines = Medicine::all();

        $locations = [
            'Nasr City, Cairo, Egypt',
            'Maadi, Cairo, Egypt',
            'Heliopolis, Cairo, Egypt',
            'Alexandria, Egypt',
            '6th of October City, Giza, Egypt',
            'Sheikh Zayed, Giza, Egypt',
            'Luxor, Egypt',
            'Aswan, Egypt',
            'Port Said, Egypt',
            'Suez, Egypt',
            'Ismailia, Egypt',
            'Tanta, Gharbia, Egypt',
            'Mansoura, Dakahlia, Egypt',
            'Zagazig, Sharqia, Egypt',
            'Assiut, Egypt',
        ];

        // Create 25 donations (all approved for donations-available endpoint)
        for ($i = 0; $i < 25; $i++) {
            $user = $users->random();
            $location = $locations[array_rand($locations)];
            $verified = true; // All donations are verified

            $donationDate = now()->subDays(rand(0, 60));

            $donation = Donation::create([
                'user_id' => $user->id,
                'location' => $location,
                'contact_info' => $this->getRandomContactInfo(),
                'verified' => $verified,
                'status' => Donation::STATUS_APPROVED, // Ensure all donations are approved
                'sealed_confirmed' => true, // All donations have sealed confirmation
                'created_at' => $donationDate,
                'updated_at' => $donationDate,
            ]);

            // Add 1-4 medicines to each donation
            $donationMedicines = $medicines->random(rand(1, 4));

            foreach ($donationMedicines as $medicine) {
                $quantity = rand(1, 5);
                $expiryDate = now()->addMonths(rand(3, 24));
                $batchNumber = 'DONATION-' . $donation->id . '-' . $medicine->id . '-' . rand(1000, 9999);

                DB::table('donation_medicines')->insert([
                    'donation_id' => $donation->id,
                    'medicine_id' => $medicine->id,
                    'quantity' => $quantity,
                    'expiry_date' => $expiryDate->format('Y-m-d'),
                    'batch_num' => $batchNumber,
                ]);
            }

            // Add some sample photos for some donations (about 60% of donations have photos)
            if (rand(1, 10) <= 6) {
                $numPhotos = rand(1, 3);
                for ($photoIndex = 0; $photoIndex < $numPhotos; $photoIndex++) {
                    DB::table('donation_photos')->insert([
                        'donation_id' => $donation->id,
                        'photo_path' => 'donations/sample_medicine_' . ($photoIndex + 1) . '.jpg',
                        'created_at' => $donationDate,
                        'updated_at' => $donationDate,
                    ]);
                }
            }
        }

        // Create some recent donations (also approved for consistency)
        for ($i = 0; $i < 8; $i++) {
            $user = $users->random();
            $location = $locations[array_rand($locations)];

            $donation = Donation::create([
                'user_id' => $user->id,
                'location' => $location,
                'contact_info' => $this->getRandomContactInfo(),
                'verified' => true, // Make these verified as well
                'status' => Donation::STATUS_APPROVED, // Ensure all donations are approved
                'sealed_confirmed' => true, // All donations have sealed confirmation
                'created_at' => now()->subDays(rand(1, 7)), // Last week
                'updated_at' => now()->subDays(rand(1, 7)),
            ]);

            $donationMedicines = $medicines->random(rand(1, 3));

            foreach ($donationMedicines as $medicine) {
                $quantity = rand(1, 3);
                $expiryDate = now()->addMonths(rand(6, 18));
                $batchNumber = 'RECENT-DONATION-' . $donation->id . '-' . $medicine->id . '-' . rand(1000, 9999);

                DB::table('donation_medicines')->insert([
                    'donation_id' => $donation->id,
                    'medicine_id' => $medicine->id,
                    'quantity' => $quantity,
                    'expiry_date' => $expiryDate->format('Y-m-d'),
                    'batch_num' => $batchNumber,
                ]);
            }

            // Add photos for recent donations (about 70% have photos since they're newer)
            if (rand(1, 10) <= 7) {
                $numPhotos = rand(1, 2);
                $recentDate = now()->subDays(rand(1, 7));
                for ($photoIndex = 0; $photoIndex < $numPhotos; $photoIndex++) {
                    DB::table('donation_photos')->insert([
                        'donation_id' => $donation->id,
                        'photo_path' => 'donations/recent_medicine_' . ($photoIndex + 1) . '.jpg',
                        'created_at' => $recentDate,
                        'updated_at' => $recentDate,
                    ]);
                }
            }
        }
    }

    private function getRandomContactInfo(): string
    {
        $contactOptions = [
            'Phone: +20 10 1234 5678, Email: ahmed.donor@gmail.com, Available: 9 AM - 6 PM',
            'Phone: +20 11 2345 6789, WhatsApp: +20 11 2345 6789, Best time: Morning',
            'Phone: +20 12 3456 7890, Email: fatma.medicine@yahoo.com, Available weekdays',
            'Phone: +20 15 4567 8901, WhatsApp: Available, Contact: Mohamed',
            'Phone: +20 10 9876 5432, Email: sara.help@hotmail.com, Evening preferred',
            'Phone: +20 11 8765 4321, Available: 10 AM - 4 PM, Contact: Omar',
            'Phone: +20 12 7654 3210, WhatsApp: +20 12 7654 3210, Weekends available',
            'Phone: +20 15 6543 2109, Email: nour.donate@gmail.com, Anytime',
            'Phone: +20 10 5555 1111, WhatsApp: Available, Contact: Amira',
            'Phone: +20 11 6666 2222, Email: hassan.med@outlook.com, Flexible timing',
            'Phone: +20 12 7777 3333, Available: 8 AM - 8 PM, Contact: Yasmin',
            'Phone: +20 15 8888 4444, WhatsApp: +20 15 8888 4444, Contact: Khaled',
        ];

        return $contactOptions[array_rand($contactOptions)];
    }
}
