<?php

namespace App\Actions\Account;

use App\Enums\Role;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\Review;
use App\Models\User;
use App\Support\ActivityRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Account deletion for a restaurant. Orders, payments and bookings are tax and
 * accounting records the business must keep for six years, so they stay with
 * their money, dates and lines intact. What goes is the identity: the user row,
 * saved addresses, cart, points, passkeys and sessions are deleted, and the name,
 * email, phone and address lines are stripped from the kept records.
 */
class EraseAccount
{
    public const REMOVED_NAME = 'Deleted customer';

    public function __construct(private readonly ActivityRecorder $activity) {}

    /** The last owner cannot leave, or nobody could run the restaurant. */
    public function ensureCanErase(User $user): void
    {
        if ($user->role === Role::Owner && User::query()->where('role', Role::Owner)->count() <= 1) {
            throw ValidationException::withMessages([
                'password' => __('account.erase.last_owner'),
            ]);
        }
    }

    public function handle(User $user): void
    {
        $this->ensureCanErase($user);

        DB::transaction(function () use ($user) {
            Order::query()->where('user_id', $user->id)->update([
                'contact' => json_encode(['name' => self::REMOVED_NAME, 'email' => '', 'phone' => '']),
                'address' => null,
                'notes' => null,
                'user_id' => null,
            ]);

            Reservation::query()->where('user_id', $user->id)->update([
                'name' => self::REMOVED_NAME,
                'email' => '',
                'phone' => '',
                'notes' => null,
                'user_id' => null,
            ]);

            Review::query()->where('user_id', $user->id)->update([
                'author_name' => __('account.erase.former_customer'),
                'author_area' => null,
                'user_id' => null,
            ]);

            DB::table('sessions')->where('user_id', $user->id)->delete();

            // Written before the row goes and without the person's id or details.
            $this->activity->record('account.erased', 'A customer account was deleted at their request.', by: null, automated: true);

            // Addresses, cart, points, passkeys, shifts cascade; other history is set null.
            $user->delete();
        });
    }
}
