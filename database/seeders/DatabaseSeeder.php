<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        \App\Models\Category::factory(5)->create();
        \App\Models\Product::factory(20)->create();
        \App\Models\Customer::factory(15)->create();

        \App\Models\Order::factory(50)
            ->has(\App\Models\OrderItem::factory()->count(rand(2, 5)))
            ->create()
            ->each(function ($order) {
                // Update total_amount
                $total = $order->orderItems->sum(fn($item) => $item->quantity * $item->unit_price);
                $order->update(['total_amount' => $total]);
            });

        $this->call(AdminUserSeeder::class);
    }
}
