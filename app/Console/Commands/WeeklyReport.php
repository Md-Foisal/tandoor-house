<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Mail\WeeklyReport as WeeklyReportMail;
use App\Models\User;
use App\Reports\Digest;
use App\Reports\ReportCsv;
use App\Reports\ReportRange;
use App\Reports\ReportSections;
use App\Support\Automation;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class WeeklyReport extends Command
{
    protected $signature = 'reports:weekly
        {--week-of= : Report the Monday to Sunday week containing this date (Y-m-d) instead of last week}
        {--force : Send again even if the week was already reported}';

    protected $description = 'Email the owners last week\'s numbers, with the spreadsheets attached.';

    public function handle(Automation $automation): int
    {
        if (! $automation->enabled(Automation::WEEKLY_REPORT)) {
            $this->components->info('The weekly report is switched off.');

            return self::SUCCESS;
        }

        $timezone = (string) config('app.timezone');
        $monday = $this->option('week-of')
            ? CarbonImmutable::parse((string) $this->option('week-of'), $timezone)->startOfWeek()
            : CarbonImmutable::now($timezone)->startOfWeek()->subWeek();
        $from = $monday->format('Y-m-d');

        $owners = User::query()->where('role', Role::Owner)->whereNotNull('email')->orderBy('id')->get();

        if ($owners->isEmpty()) {
            $this->components->warn('There is no owner to email.');

            return self::SUCCESS;
        }

        $marker = "reports:weekly:{$from}";
        $claimed = $this->option('force')
            ? Cache::put($marker, true, now()->addDays(14))
            : Cache::add($marker, true, now()->addDays(14));

        if (! $claimed) {
            $this->components->info("The report for the week of {$from} has already been sent.");

            return self::SUCCESS;
        }

        $to = $monday->addDays(6)->format('Y-m-d');
        $range = ReportRange::resolve('custom', $from, $to);
        $report = Digest::build($range);
        $url = route('staff.reports.index', ['period' => 'custom', 'from' => $from, 'to' => $to]);

        $csv = [];

        foreach (ReportSections::KEYS as $section) {
            $csv[ReportCsv::filename($section, $range)] = ReportCsv::contents($section, $range);
        }

        foreach ($owners as $owner) {
            Mail::to($owner)->queue(new WeeklyReportMail($report, explode(' ', trim($owner->name))[0], $url, $csv));
        }

        $automation->log(
            'automation.report.weekly',
            trans_choice('automations.reports.logged.weekly', $owners->count(), ['label' => $range->label(), 'count' => $owners->count()]),
            properties: ['from' => $from, 'to' => $to, 'recipients' => $owners->count()],
        );

        $this->components->info("Weekly report for {$from} queued for {$owners->count()} owner(s).");

        return self::SUCCESS;
    }
}
