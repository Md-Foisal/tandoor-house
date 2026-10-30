<?php

namespace App\Support;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;

/**
 * The one door every automation goes through: it checks the owner's switch
 * in Settings and writes what happened to the activity log as automated.
 */
class Automation
{
    public const AUTO_ACCEPT = 'auto_accept';

    public const NOTIFICATIONS = 'notifications';

    public const STOCK_DEDUCTION = 'stock_deduction';

    public const RESERVATION_REMINDERS = 'reservation_reminders';

    public const NO_SHOW_RELEASE = 'no_show_release';

    public const DAILY_CLOSE = 'daily_close';

    public const WEEKLY_REPORT = 'weekly_report';

    public const REVIEW_REQUESTS = 'review_requests';

    public const LOYALTY = 'loyalty';

    public const ABANDONED_CART = 'abandoned_cart';

    public const PREP_ESTIMATES = 'prep_estimates';

    public function __construct(
        private readonly Settings $settings,
        private readonly ActivityRecorder $activity,
    ) {}

    public function enabled(string $automation): bool
    {
        return $this->settings->enabled($automation);
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    public function log(string $event, string $description, ?Model $subject = null, array $properties = []): ActivityLog
    {
        return $this->activity->record($event, $description, $subject, $properties, automated: true);
    }
}
