<?php

namespace App\Http\Controllers\Staff\Reservations;

use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\Reservations\StoreStaffReservationRequest;
use App\Models\Reservation;
use App\Services\TableAvailability;
use App\Support\ActivityRecorder;
use App\Support\CodeGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class StaffBookingController extends Controller
{
    public function __construct(
        private readonly TableAvailability $availability,
        private readonly ActivityRecorder $log,
    ) {}

    public function store(StoreStaffReservationRequest $request): RedirectResponse
    {
        $start = $request->startsAt() ?? throw ValidationException::withMessages(['time' => __('booking.errors.time_unavailable')]);
        $party = $request->integer('party_size');
        $waived = $request->wantsDepositWaived();
        $deposit = $waived ? 0 : $this->availability->depositFor($party);

        $reservation = DB::transaction(function () use ($request, $start, $party, $deposit) {
            $table = $this->availability->findTable($start, $party, lock: true);

            if ($table === null) {
                throw ValidationException::withMessages(['time' => __('booking.errors.taken')]);
            }

            return Reservation::create([
                'reference' => CodeGenerator::unique(Reservation::class, 'reference', 'R', 5),
                'name' => $request->string('name')->trim()->toString(),
                'email' => $request->string('email')->toString(),
                'phone' => $request->string('phone')->toString(),
                'party_size' => $party,
                'starts_at' => $start,
                'ends_at' => $start->addMinutes($this->availability->durationMinutes()),
                'dining_table_id' => $table->id,
                'status' => $deposit > 0 ? ReservationStatus::PendingDeposit : ReservationStatus::Confirmed,
                'deposit' => $deposit,
                'deposit_status' => $deposit > 0 ? PaymentStatus::Pending : null,
                'occasion' => $request->filled('occasion') ? $request->string('occasion')->toString() : null,
                'notes' => $request->filled('notes') ? $request->string('notes')->trim()->toString() : null,
            ]);
        });

        $this->log->record('reservation.created', __('staff_reservations.log.created', ['name' => $reservation->name, 'party' => $party]), $reservation, $waived && $this->availability->depositFor($party) > 0 ? ['deposit_waived' => true] : [], $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __($deposit > 0 ? 'staff_reservations.flash.created_deposit' : 'staff_reservations.flash.created', ['name' => $reservation->name])]);

        return redirect()->route('staff.reservations.index', ['date' => $start->toDateString()]);
    }
}
