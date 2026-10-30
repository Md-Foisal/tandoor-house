<?php

use App\Broadcasting\Channels;
use App\Enums\StaffArea;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Gate;

Broadcast::channel(Channels::STAFF_ORDERS, fn (User $user) => Gate::forUser($user)->allows('staff.'.StaffArea::Orders->value));

Broadcast::channel(Channels::STAFF_KITCHEN, fn (User $user) => Gate::forUser($user)->allows('staff.'.StaffArea::Kitchen->value));

Broadcast::channel(Channels::STAFF_FLOOR, fn (User $user) => Gate::forUser($user)->allows('staff.'.StaffArea::Floor->value));

Broadcast::channel(Channels::STAFF_RESERVATIONS, fn (User $user) => Gate::forUser($user)->allows('staff.'.StaffArea::Reservations->value));

// A rider hears only their own runs; managers and owners can watch any rider.
Broadcast::channel(Channels::STAFF_DELIVERIES, function (User $user, string $riderId) {
    if (! Gate::forUser($user)->allows('staff.'.StaffArea::Deliveries->value)) {
        return false;
    }

    return $user->role->isManagement() || $user->id === (int) $riderId;
});

Broadcast::channel(Channels::ORDER, fn (User $user, string $number) => Order::query()
    ->where('number', $number)
    ->where('user_id', $user->id)
    ->exists());
