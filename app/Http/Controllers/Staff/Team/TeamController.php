<?php

namespace App\Http\Controllers\Staff\Team;

use App\Actions\Team\RotaBoard;
use App\Actions\Team\TeamRules;
use App\Actions\Team\TipSplit;
use App\Actions\Team\WeekRange;
use App\Actions\Team\WeekReport;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    private const TABS = ['people', 'rota', 'attendance', 'tips'];

    public function index(Request $request, RotaBoard $rota, WeekReport $report, TipSplit $tips): Response
    {
        /** @var User $actor */
        $actor = $request->user();
        $tab = in_array($request->query('tab'), self::TABS, true) ? (string) $request->query('tab') : 'people';
        $week = WeekRange::from($request->query('week') === null ? null : (string) $request->query('week'));
        $current = WeekRange::from(null);

        return Inertia::render('staff/team/index', [
            'tab' => $tab,
            'week' => [
                'start' => $week->start->toDateString(),
                'label' => $week->label(),
                'previous' => $week->previous()->toDateString(),
                'next' => $week->next()->toDateString(),
                'is_current' => $week->isCurrent(),
            ],
            'me' => ['id' => $actor->id, 'role' => $actor->role->value],
            'assignable_roles' => array_map(fn (Role $role) => $role->value, TeamRules::assignable($actor)),
            'people' => fn () => $tab === 'people' ? $this->people($actor, $report, $current) : null,
            'rota' => fn () => $tab === 'rota' ? $rota->build($week, $actor) : null,
            'attendance' => fn () => $tab === 'attendance' ? $report->build($week) : null,
            'tips' => fn () => $tab === 'tips' ? TipsController::overview($actor, $tips, $report, $week) : null,
        ]);
    }

    /**
     * @return array{total: int, orders: int, minutes: int, shares: array<int, array<string, mixed>>, unfixed: int}
     */
    public static function split(TipSplit $tips, WeekReport $report, WeekRange $week): array
    {
        $pot = $tips->pot($week);
        $all = collect($report->build($week));
        $people = $all->filter(fn (array $p) => $p['clocked_minutes'] > 0)->values();
        $shares = TipSplit::allocate($pot['total'], $people->pluck('clocked_minutes', 'id')->all());

        return [
            ...$pot,
            'minutes' => (int) $people->sum('clocked_minutes'),
            'unfixed' => (int) $all->sum('missed'),
            'shares' => $people->map(fn (array $p) => [
                'id' => $p['id'],
                'name' => $p['name'],
                'role' => $p['role'],
                'minutes' => $p['clocked_minutes'],
                'share' => $shares[$p['id']] ?? 0,
            ])->all(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function people(User $actor, WeekReport $report, WeekRange $week): array
    {
        $hours = collect($report->build($week))->pluck('clocked_minutes', 'id');
        $onShift = Attendance::query()->whereNull('clocked_out_at')->where('clocked_in_at', '>', now()->subHours(WeekReport::MISSED_AFTER_HOURS))->pluck('user_id')->all();

        return User::query()
            ->where('role', '!=', Role::Customer)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'phone', 'role'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role->value,
                'on_shift' => in_array($user->id, $onShift, true),
                'minutes' => (int) ($hours[$user->id] ?? 0),
                'can_edit' => TeamRules::canEdit($actor, $user),
                'can_remove' => TeamRules::canRemove($actor, $user),
                'is_me' => $user->is($actor),
            ])
            ->all();
    }
}
