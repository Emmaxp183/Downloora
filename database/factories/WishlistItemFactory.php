<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WishlistItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WishlistItem>
 */
class WishlistItemFactory extends Factory
{
    protected $model = WishlistItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $url = $this->faker->url();

        return [
            'user_id' => User::factory(),
            'url' => $url,
            'url_hash' => hash('sha256', $url),
            'source_type' => 'media',
            'source_domain' => parse_url($url, PHP_URL_HOST),
            'title' => $this->faker->sentence(3),
        ];
    }
}
