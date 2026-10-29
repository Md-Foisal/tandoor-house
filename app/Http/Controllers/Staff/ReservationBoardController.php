<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Staff\Reservations\BoardData;
use App\Http\Requests\Reservations\StoreReservationRequest;
use App\Services\TableAvailability;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReservationBoardController extends Controller
{
    public function __construct(
        private readonly BoardData $board,
        private readonly TableAvailability $availability,
    ) {}

    public function index(Request $request, Settings $settings): Response
    {
        $now = CarbonImmutable::now(config('app.timezone'));
        $view = $request->query('view') === 'week' ? 'week' : 'day';
        $date = $this->dateFrom($request, $now);
        $step = $view === 'week' ? 7 : 1;
        $loaded = null;
        $reservations = function () use (&$loaded, $date) {
            return $loaded ??= $this->board->reservationsOn($date);
        };

        return Inertia::render('staff/reservations/index', [
            'view' => $view,
            'date' => $date->toDateString(),
            'today' => $now->toDateString(),
            'weekday' => $date->translatedFormat('l'),
            'date_label' => $date->translatedFormat('l j F Y'),
            'prev' => $date->subDays($step)->toDateString(),
            'next' => $date->addDays($step)->toDateString(),
            'is_today' => $date->isSameDay($now),
            'now' => [
                'iso' => $now->toIso8601String(),
                'minute' => $now->hour * 60 + $now->minute,
            ],
            'rules' => [
                'max_party' => $this->availability->maxParty(),
                'deposit_from_party' => (int) $settings->get('reservation.deposit_from_party', 6),
                'deposit_per_head' => (int) $settings->get('reservation.deposit_per_head', 1000),
                'duration_minutes' => $this->availability->durationMinutes(),
                'no_show_minutes' => (int) $settings->get('reservation.no_show_minutes', 20),
            ],
            'occasions' => StoreReservationRequest::OCCASIONS,
            'can_waive_deposit' => (bool) $request->user()?->role->isManagement(),
            'tables' => fn () => $this->board->tableRows(),
            'periods' => fn () => $this->board->periods($date),
            'bookings' => fn () => $view === 'day' ? $this->board->bookings($date, $reservations()) : [],
            'services' => fn () => $view === 'day' ? $this->board->services($date, $reservations(), $now) : [],
            'week' => fn () => $view === 'week' ? $this->board->week($date, $now) : [],
        ]);
    }

    private function dateFrom(Request $request, CarbonImmutable $now): CarbonImmutable
    {
        $value = $request->query('date');

        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return $now->startOfDay();
        }

        try {
            return $this->availability->parseDate($value) ?? $now->startOfDay();
        } catch (\Throwable) {
            return $now->startOfDay();
        }
    }
}
