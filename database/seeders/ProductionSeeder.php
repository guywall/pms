<?php

namespace Database\Seeders;

use App\Models\ProductionStage;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Safe to run on any environment, any number of times.
 * Creates: roles, default pipeline stages, and one super-admin.
 *
 * Admin credentials resolution order:
 *   1. SEED_ADMIN_EMAIL / SEED_ADMIN_PASSWORD env vars (scripts/install.sh sets these)
 *   2. An interactive prompt (production terminals only — skipped in local env
 *      and non-TTY runs)
 *   3. Defaults: admin@example.test / "password" locally; a generated password
 *      (printed once) in other non-interactive contexts.
 */
class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        // ----- Roles & permissions -----
        $roles = ['super_admin', 'manager', 'artworker', 'print_operator', 'embroidery_operator', 'storekeeper', 'customer'];
        foreach ($roles as $role) {
            \Spatie\Permission\Models\Role::firstOrCreate(['name' => $role]);
        }

        // ----- Default pipeline stages -----
        $stages = [
            ['key' => 'new_order', 'name' => 'New order', 'position' => 1, 'is_default_start' => true],
            ['key' => 'artwork', 'name' => 'Artwork', 'position' => 2],
            ['key' => 'pre_production', 'name' => 'Pre-production', 'position' => 3, 'gate_requires_final_artwork' => true, 'gate_requires_stock' => true, 'gate_requires_digitised_file' => true],
            ['key' => 'printing', 'name' => 'Printing', 'position' => 4, 'gate_requires_final_artwork' => true],
            ['key' => 'embroidery', 'name' => 'Embroidery', 'position' => 5, 'gate_requires_final_artwork' => true],
            ['key' => 'finishing', 'name' => 'Finishing', 'position' => 6],
            ['key' => 'qc', 'name' => 'QC', 'position' => 7],
            ['key' => 'packing', 'name' => 'Packing', 'position' => 8],
            ['key' => 'complete', 'name' => 'Complete', 'position' => 9, 'is_terminal' => true],
        ];

        foreach ($stages as $stage) {
            ProductionStage::updateOrCreate(['key' => $stage['key']], $stage);
        }

        // ----- Super admin -----
        $email = env('SEED_ADMIN_EMAIL');
        $password = env('SEED_ADMIN_PASSWORD');

        $canPrompt = app()->runningInConsole()
            && stream_isatty(STDIN)
            && ! app()->environment('local');

        if (empty($email) && $canPrompt) {
            $askedEmail = $this->command?->ask('Admin account email', 'admin@threewalls.co.uk');
            $askedPassword = $this->command?->secret('Admin password (input hidden)');

            if ($askedEmail !== null && $askedEmail !== '') {
                $email = $askedEmail;
            }
            if (is_string($askedPassword) && $askedPassword !== '') {
                $password = $askedPassword;
            }
        }

        $generated = false;
        $email = $email ?: 'admin@example.test';

        if (empty($password)) {
            if (app()->environment('local')) {
                $password = 'password';
            } else {
                $password = bin2hex(random_bytes(10));
                $generated = true;
            }
        }

        $admin = User::firstOrCreate(
            ['email' => $email],
            ['name' => 'Administrator', 'password' => Hash::make($password), 'is_staff' => true]
        );
        $admin->assignRole('super_admin');

        $this->command?->info(
            "Seeded pipeline stages, roles and admin account: {$email}"
            . ($generated ? " — generated password: {$password} (save it now)" : '')
        );
    }
}
