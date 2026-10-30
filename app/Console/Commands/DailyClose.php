<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Mail\DailyCloseReport;
use App\Models\User;
use App\Reports\Digest;
use App\Reports\ReportRange;
use App\Support\Automation;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class DailyClose extends Command
{
    protected $signature = 'reports:daily-close
        {--date= : Close this day (Y-m-d) instead of today}
        {--force : Send again even if the day was already closed}';

    protected $description = 'Email the owners the day\'s takings at closing time.';

    public function handle(Automation $automation): int
    {
        if (! $automation->enabled(Automation::DAILY_CLOSE)) {
            $this->components->info('The daily close report is switched off.');

            return self::SUCCESS;
        }

        $date = $this->option('date')
            ? CarbonImmutable::parse((string) $this->option('date'), config('app.timezone'))->format('Y-m-d')
            : CarbonImmutable::now(config('app.timezone'))->format('Y-m-d');

        $owners = User::query()->where('role', Role::Owner)->whereNotNull('email')->orderBy('id')->get();

        if ($owners->isEmpty()) {
            $this->components->warn('There is no owner to email.');

            return self::SUCCESS;
        }

        $marker = "reports:daily-close:{$date}";
        $claimed = $this->option('force')
            ? Cache::put($marker, true, now()->addDays(3))
            : Cache::add($marker, true, now()->addDays(3));

        if (! $claimed) {
            $this->components->info("The close for {$date} has already been sent.");

            return self::SUCCESS;
        }

        $range = ReportRange::resolve('custom', $date, $date);
        $report = Digest::build($range);
        $url = route('staff.reports.index', ['period' => 'custom', 'from' => $date, 'to' => $date]);

        foreach ($owners as $owner) {
            Mail::to($owner)->queue(new DailyCloseReport($report, explode(' ', trim($owner->name))[0], $url));
        }

        $automation->log(
            'automation.report.daily_close',
            trans_choice('automations.reports.logged.daily', $owners->count(), ['date' => $range->label(), 'count' => $owners->count()]),
            properties: ['date' => $date, 'recipients' => $owners->count()],
        );

        $this->components->info("Daily close for {$date} queued for {$owners->count()} owner(s).");

        return self::SUCCESS;
    }
}
