<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Events\ReservationUpdated;
use App\Http\Requests\Reservations\StoreReservationRequest;
use App\Models\Reservation;
use App\Policies\ReservationPolicy;
use App\Services\ReservationCancellation;
use App\Services\TableAvailability;
use App\Support\CodeGenerator;
use App\Support\DepositHold;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Lang;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ReservationController extends Controller
{
    private const DEFAULT_PARTY = 2;

    public function __construct(private readonly TableAvailability $availability) {}

    public function create(Request $request, Settings $settings): Response
    {
        $days = $this->availability->days();
        $party = $this->partyFrom($request);
        $date = $this->dateFrom($request, $days);

        return Inertia::render('book/create', [
            'rules' => [
                'max_party' => $this->availability->maxParty(),
                'deposit_from_party' => (int) $settings->get('reservation.deposit_from_party', 6),
                'deposit_per_head' => (int) $settings->get('reservation.deposit_per_head', 1000),
                'duration_minutes' => $this->availability->durationMinutes(),
                'no_show_minutes' => (int) $settings->get('reservation.no_show_minutes', 20),
            ],
            'days' => $days,
            'query' => ['party' => $party, 'date' => $date],
            'slots' => fn () => $date === null ? [] : $this->availability->slots($this->availability->parseDate($date) ?? now()->toImmutable(), $party),
            'contact' => [
                'name' => $request->user()->name ?? '',
                'email' => $request->user()->email ?? '',
                'phone' => $request->user()->phone ?? '',
                'marketing_opt_in' => (bool) ($request->user()->marketing_opt_in ?? false),
            ],
            'occasions' => StoreReservationRequest::OCCASIONS,
        ]);
    }

    public function slots(Request $request): JsonResponse
    {
        $data = $request->validate([
            'party' => ['required', 'integer', 'min:1', 'max:'.$this->availability->maxParty()],
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $date = $this->availability->parseDate($data['date']);

        return response()->json([
            'slots' => $date === null ? [] : $this->availability->slots($date, (int) $data['party']),
        ]);
    }

    public function store(StoreReservationRequest $request): RedirectResponse
    {
        $start = $request->startsAt() ?? throw ValidationException::withMessages(['time' => __('booking.errors.time_unavailable')]);
        $party = (int) $request->integer('party_size');
        $deposit = $this->availability->depositFor($party);

        // The check in the form request can lose a race, so look again under a lock.
        $reservation = DB::transaction(function () use ($request, $start, $party, $deposit) {
            $table = $this->availability->findTable($start, $party, lock: true);

            if ($table === null) {
                throw ValidationException::withMessages(['time' => __('booking.errors.taken')]);
            }

            return Reservation::create([
                'reference' => CodeGenerator::unique(Reservation::class, 'reference', 'R', 5),
                'user_id' => $request->user()?->id,
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

        ReservationUpdated::dispatch($reservation);

        if ($request->boolean('marketing_opt_in') && $request->user() !== null) {
            $request->user()->update(['marketing_opt_in' => true]);
        }

        $request->session()->put(ReservationPolicy::SESSION_KEY, array_values(array_unique([
            ...(array) $request->session()->get(ReservationPolicy::SESSION_KEY, []),
            $reservation->reference,
        ])));

        if ($deposit > 0) {
            return redirect()->route('book.pay', $reservation);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('booking.flash.confirmed')]);

        return redirect()->route('book.show', $reservation);
    }

    public function show(Request $request, Reservation $reservation, Settings $settings, ReservationCancellation $cancellation, DepositHold $hold): Response
    {
        // A valid signed link (from the confirmation email) opens the booking
        // in any browser; remembering it lets the same guest pay or cancel.
        if ($request->hasValidSignature()) {
            $request->session()->put(ReservationPolicy::SESSION_KEY, array_values(array_unique([
                ...(array) $request->session()->get(ReservationPolicy::SESSION_KEY, []),
                $reservation->reference,
            ])));
        }

        Gate::authorize('view', $reservation);

        $reservation->load('diningTable');
        $start = CarbonImmutable::instance($reservation->starts_at)->setTimezone(config('app.timezone'));
        $end = CarbonImmutable::instance($reservation->ends_at)->setTimezone(config('app.timezone'));

        return Inertia::render('book/show', [
            'reservation' => [
                'reference' => $reservation->reference,
                'name' => $reservation->name,
                'party_size' => $reservation->party_size,
                'date_label' => $start->translatedFormat('l j F'),
                'time' => $start->format('H:i'),
                'ends_at' => $end->format('H:i'),
                'starts_at' => $start->toIso8601String(),
                'area' => $reservation->diningTable?->area,
                'no_show_minutes' => (int) $settings->get('reservation.no_show_minutes', 20),
                'status' => $reservation->status->value,
                'deposit' => $reservation->deposit,
                'deposit_status' => $reservation->deposit_status?->value,
                'occasion' => $this->occasionLabel($reservation->occasion),
                'notes' => $reservation->notes,
            ],
            'pay' => $reservation->status === ReservationStatus::PendingDeposit && $reservation->deposit_status !== PaymentStatus::Paid
                ? [
                    'url' => route('book.pay', $reservation),
                    'expires_at' => $hold->until($reservation)->toIso8601String(),
                    'expired' => $hold->expired($reservation),
                ]
                : null,
            'cancel' => $cancellation->terms($reservation, Gate::allows('cancel', $reservation)),
        ]);
    }

    private function partyFrom(Request $request): int
    {
        $party = $request->integer('party', self::DEFAULT_PARTY);

        return min(max($party, 1), $this->availability->maxParty() + 1);
    }

    /**
     * @param  list<array{date: string, status: string}>  $days
     */
    private function dateFrom(Request $request, array $days): ?string
    {
        $wanted = (string) $request->query('date', '');

        foreach ($days as $day) {
            if ($day['date'] === $wanted && $day['status'] === 'open') {
                return $wanted;
            }
        }

        return collect($days)->firstWhere('status', 'open')['date'] ?? null;
    }

    /**
     * Occasions are stored as keys; rows from before that hold the label itself.
     */
    private function occasionLabel(?string $occasion): ?string
    {
        if ($occasion === null) {
            return null;
        }

        return Lang::has('booking.occasions.'.$occasion) ? __('booking.occasions.'.$occasion) : $occasion;
    }
}
