<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A confirmed table for two, tomorrow at 7.30pm.
 *
 * @extends Factory<Reservation>
 */
class ReservationFactory extends Factory
{
    public function definition(): array
    {
        $start = now()->addDay()->setTime(19, 30);

        return [
            'reference' => 'R'.Str::upper(fake()->unique()->lexify('??????')),
            'user_id' => null,
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => '07'.fake()->numerify('### ######'),
            'party_size' => 2,
            'starts_at' => $start,
            'ends_at' => $start->copy()->addMinutes(105),
            'dining_table_id' => null,
            'status' => ReservationStatus::Confirmed,
            'deposit' => 0,
            'deposit_status' => null,
            'occasion' => null,
            'notes' => null,
        ];
    }

    public function at(Carbon|string $start): static
    {
        return $this->state(function () use ($start) {
            $start = Carbon::parse($start);

            return ['starts_at' => $start, 'ends_at' => $start->copy()->addMinutes(105)];
        });
    }

    /**
     * Parties of six or more pay £10 a head up front.
     */
    public function forParty(int $size): static
    {
        return $this->state(fn () => [
            'party_size' => $size,
            'deposit' => $size >= 6 ? $size * 1000 : 0,
            'deposit_status' => $size >= 6 ? PaymentStatus::Paid : null,
        ]);
    }

    public function pendingDeposit(int $size = 6): static
    {
        return $this->forParty($size)->state(fn () => [
            'status' => ReservationStatus::PendingDeposit,
            'deposit_status' => PaymentStatus::Pending,
        ]);
    }

    public function status(ReservationStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }

    public function seated(): static
    {
        return $this->state(fn () => [
            'status' => ReservationStatus::Seated,
            'starts_at' => now()->subMinutes(25),
            'ends_at' => now()->addMinutes(80),
            'seated_at' => now()->subMinutes(22),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => ReservationStatus::Completed,
            'starts_at' => now()->subDay()->setTime(19, 30),
            'ends_at' => now()->subDay()->setTime(21, 15),
        ]);
    }

    public function occasion(string $occasion): static
    {
        return $this->state(fn () => ['occasion' => $occasion]);
    }
}
