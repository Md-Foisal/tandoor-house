<?php

namespace Database\Factories;

use App\Models\Review;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => null,
            'user_id' => null,
            'author_name' => fake()->firstName().' '.fake()->randomLetter().'.',
            'author_area' => fake()->randomElement(['Bethnal Green', 'Hackney', 'Whitechapel', 'Bow', 'Shoreditch']),
            'rating' => fake()->randomElement([4, 5, 5, 5]),
            'body' => fake()->sentence(14),
            'reply' => null,
            'is_published' => true,
        ];
    }

    public function rating(int $rating): static
    {
        return $this->state(fn () => ['rating' => $rating]);
    }

    public function unpublished(): static
    {
        return $this->state(fn () => ['is_published' => false]);
    }

    public function replied(string $reply = 'Thank you for telling us. Tom, manager.'): static
    {
        return $this->state(fn () => ['reply' => $reply]);
    }
}
