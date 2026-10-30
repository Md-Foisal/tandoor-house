<?php

namespace App\Http\Controllers\Staff\Team;

use App\Actions\Team\TipSplit;
use App\Actions\Team\WeekRange;
use App\Actions\Team\WeekReport;
use App\Http\Controllers\Controller;
use App\Support\Csv;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TipsController extends Controller
{
    public function export(Request $request, TipSplit $tips, WeekReport $report): StreamedResponse
    {
        $week = WeekRange::from($request->query('week') === null ? null : (string) $request->query('week'));
        $split = TeamController::split($tips, $report, $week);

        return Csv::download(
            'tips-'.$week->start->toDateString().'.csv',
            array_map(fn (string $key) => __('staff_team.csv.tips.'.$key), ['name', 'role', 'hours', 'share']),
            array_map(fn (array $row) => [
                $row['name'],
                __('roles.'.$row['role']),
                number_format($row['minutes'] / 60, 2, '.', ''),
                number_format($row['share'] / 100, 2, '.', ''),
            ], $split['shares']),
        );
    }
}
