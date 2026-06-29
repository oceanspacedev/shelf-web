<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // \App\Models\User::factory(10)->create();

        // \App\Models\User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);

        $superAdmin = $this->seedSuperAdmin();

        if ($superAdmin) {
            Artisan::call('shield:super-admin', ['--user' => $superAdmin->id]);
        }

        Artisan::call('shield:generate', ['--all' => true, '--ignore-existing-policies' => true]);

        // Panggil seeder lainnya
        $this->call([
            CategorySeeder::class,
            BusinessEntitySeeder::class,
            JobTitleSeeder::class,
            BrandSeeder::class,
            // AssetLocationSeeder::class,
            // AssetSeeder::class,
        ]);
    }

    private function seedSuperAdmin(): ?User
    {
        $password = env('SEED_SUPER_ADMIN_PASSWORD');

        if (blank($password) && app()->isProduction()) {
            $this->command?->warn('Skipping super admin seed: set SEED_SUPER_ADMIN_PASSWORD for production seeding.');

            return null;
        }

        return User::updateOrCreate(
            ['username' => env('SEED_SUPER_ADMIN_USERNAME', 'admin')],
            [
                'name' => env('SEED_SUPER_ADMIN_NAME', 'Super Admin'),
                'email' => env('SEED_SUPER_ADMIN_EMAIL', 'admin@dev.com'),
                'email_verified_at' => Carbon::now(),
                'password' => Hash::make($password ?: 'password'),
            ],
        );
    }
}
