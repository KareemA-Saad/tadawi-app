<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Admin User
        User::create([
            'name' => 'Admin User',
            'email' => 'admin@tadawi.com',
            'password' => Hash::make('Strong@Pass123'),
            'phone_number' => '+20 10 0000 0001',
            'role' => 'admin',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        // Create Doctors
        $doctors = [
            [
                'name' => 'Dr. Ahmed Hassan',
                'email' => 'ahmed.hassan@tadawi.com',
                'password' => Hash::make('Strong@Pass123'),
                'phone_number' => '+20 10 0000 0002',
                'role' => 'doctor',
                'status' => 'active',
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Dr. Fatma Ali',
                'email' => 'fatma.ali@tadawi.com',
                'password' => Hash::make('Strong@Pass123'),
                'phone_number' => '+20 10 0000 0003',
                'role' => 'doctor',
                'status' => 'active',
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Dr. Mohamed Ibrahim',
                'email' => 'mohamed.ibrahim@tadawi.com',
                'password' => Hash::make('Strong@Pass123'),
                'phone_number' => '+20 10 0000 0004',
                'role' => 'doctor',
                'status' => 'active',
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Dr. Sara Mahmoud',
                'email' => 'sara.mahmoud@tadawi.com',
                'password' => Hash::make('Strong@Pass123'),
                'phone_number' => '+20 10 0000 0005',
                'role' => 'doctor',
                'status' => 'active',
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Dr. Omar Khalil',
                'email' => 'omar.khalil@tadawi.com',
                'password' => Hash::make('Strong@Pass123'),
                'phone_number' => '+20 10 0000 0006',
                'role' => 'doctor',
                'status' => 'active',
                'email_verified_at' => now(),
            ],
        ];

        foreach ($doctors as $doctor) {
            User::create($doctor);
        }

        // Create Pharmacies
        $pharmacies = [
            [
                'name' => 'Cairo Central Pharmacy',
                'email' => 'cairo.central@tadawi.com',
                'password' => Hash::make('Strong@Pass123'),
                'phone_number' => '+20 10 0000 0007',
                'role' => 'pharmacy',
                'status' => 'active',
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Alexandria Medical Center',
                'email' => 'alex.medical@tadawi.com',
                'password' => Hash::make('Strong@Pass123'),
                'phone_number' => '+20 10 0000 0008',
                'role' => 'pharmacy',
                'status' => 'active',
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Giza Health Pharmacy',
                'email' => 'giza.health@tadawi.com',
                'password' => Hash::make('Strong@Pass123'),
                'phone_number' => '+20 10 0000 0009',
                'role' => 'pharmacy',
                'status' => 'active',
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Luxor Family Pharmacy',
                'email' => 'luxor.family@tadawi.com',
                'password' => Hash::make('Strong@Pass123'),
                'phone_number' => '+20 10 0000 0010',
                'role' => 'pharmacy',
                'status' => 'active',
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Aswan Community Pharmacy',
                'email' => 'aswan.community@tadawi.com',
                'password' => Hash::make('Strong@Pass123'),
                'phone_number' => '+20 10 0000 0011',
                'role' => 'pharmacy',
                'status' => 'active',
                'email_verified_at' => now(),
            ],
        ];

        foreach ($pharmacies as $pharmacy) {
            User::create($pharmacy);
        }

        // Create Patients
        $patients = [
            [
                'name' => 'Youssef Mohamed',
                'email' => 'youssef.mohamed@email.com',
                'password' => Hash::make('Strong@Pass123'),
                'phone_number' => '+20 10 0000 0012',
                'role' => 'patient',
                'status' => 'active',
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Nour El-Din',
                'email' => 'nour.eldin@email.com',
                'password' => Hash::make('Strong@Pass123'),
                'phone_number' => '+20 10 0000 0013',
                'role' => 'patient',
                'status' => 'active',
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Mariam Hassan',
                'email' => 'mariam.hassan@email.com',
                'password' => Hash::make('Strong@Pass123'),
                'phone_number' => '+20 10 0000 0014',
                'role' => 'patient',
                'status' => 'active',
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Khaled Abdel Rahman',
                'email' => 'khaled.abdelrahman@email.com',
                'password' => Hash::make('Strong@Pass123'),
                'phone_number' => '+20 10 0000 0015',
                'role' => 'patient',
                'status' => 'active',
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Dina Farouk',
                'email' => 'dina.farouk@email.com',
                'password' => Hash::make('Strong@Pass123'),
                'phone_number' => '+20 10 0000 0016',
                'role' => 'patient',
                'status' => 'active',
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Tarek El-Sayed',
                'email' => 'tarek.elsayed@email.com',
                'password' => Hash::make('Strong@Pass123'),
                'phone_number' => '+20 10 0000 0017',
                'role' => 'patient',
                'status' => 'active',
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Hala Mostafa',
                'email' => 'hala.mostafa@email.com',
                'password' => Hash::make('Strong@Pass123'),
                'phone_number' => '+20 10 0000 0018',
                'role' => 'patient',
                'status' => 'active',
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Amr El-Masry',
                'email' => 'amr.elmasry@email.com',
                'password' => Hash::make('Strong@Pass123'),
                'phone_number' => '+20 10 0000 0019',
                'role' => 'patient',
                'status' => 'active',
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Rania Abdel Aziz',
                'email' => 'rania.abdelaziz@email.com',
                'password' => Hash::make('Strong@Pass123'),
                'phone_number' => '+20 10 0000 0020',
                'role' => 'patient',
                'status' => 'active',
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Sherif El-Gamal',
                'email' => 'sherif.elgamal@email.com',
                'password' => Hash::make('Strong@Pass123'),
                'phone_number' => '+20 10 0000 0021',
                'role' => 'patient',
                'status' => 'active',
                'email_verified_at' => now(),
            ],
        ];

        foreach ($patients as $patient) {
            User::create($patient);
        }

        // Create some suspended users for testing
        User::create([
            'name' => 'Suspended User',
            'email' => 'suspended@tadawi.com',
            'password' => Hash::make('Strong@Pass123'),
            'phone_number' => '+20 10 0000 0022',
            'role' => 'patient',
            'status' => 'suspended',
            'email_verified_at' => null,
        ]);

        User::create([
            'name' => 'Pending Doctor',
            'email' => 'pending.doctor@tadawi.com',
            'password' => Hash::make('Strong@Pass123'),
            'phone_number' => '+20 10 0000 0023',
            'role' => 'doctor',
            'status' => 'pending',
            'email_verified_at' => null,
        ]);
    }
}
