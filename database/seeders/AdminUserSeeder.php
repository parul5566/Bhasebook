<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        if (User::where('email', 'admin@bhasebook.test')->exists()) {
            return;
        }
        User::create([
            'name' => 'Bhase Admin',
            'email' => 'admin@bhasebook.test',
            'password' => 'Admin@12345',
            'is_admin' => true,
            'email_verified_at' => now(),
            'bio' => 'Keeping Bhasebook safe and fun.',
            'default_post_visibility' => 'public',
            'profile_visibility' => 'public',
        ]);
    }
}
