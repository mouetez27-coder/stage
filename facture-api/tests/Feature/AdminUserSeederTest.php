<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_creates_default_admin_user(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseHas('users', [
            'email' => 'admin@facture.com',
            'name' => 'Admin',
        ]);

        $user = User::where('email', 'admin@facture.com')->first();

        $this->assertNotNull($user);
        $this->assertTrue(Hash::check('admin123', $user->password));
    }
}
