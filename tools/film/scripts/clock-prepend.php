<?php

// Film capture only: shifts the app's clock to the evening service without touching app code.
// Loaded through auto_prepend_file by scripts/serve.sh; FILM_CLOCK is "Y-m-d H:i" in Europe/London.
if (($target = getenv('FILM_CLOCK')) && ($root = getenv('FILM_APP_ROOT'))) {
    require_once $root.'/vendor/autoload.php';

    $offset = (new DateTimeImmutable($target, new DateTimeZone('Europe/London')))->getTimestamp() - (int) getenv('FILM_CLOCK_EPOCH');
    $shift = fn ($real) => $real->addSeconds($offset);
    Carbon\Carbon::setTestNow($shift);
    Carbon\CarbonImmutable::setTestNow($shift);
}
