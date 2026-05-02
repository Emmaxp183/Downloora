<?php

namespace Database\Factories;

use App\Models\StorageUsageEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StorageUsageEvent>
 */
class StorageUsageEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'stored_file_id' => null,
            'delta_bytes' => fake()->numberBetween(1, 1000000),
            'reason' => 'file_stored',
            'metadata' => null,
        ];
    }
}
