<?php

namespace App\Http\Controllers\Account;

use App\Actions\Account\ExportAccountData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\DataExportRequest;
use App\Support\ActivityRecorder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataExportController extends Controller
{
    public function __invoke(DataExportRequest $request, ExportAccountData $export, ActivityRecorder $activity): StreamedResponse
    {
        $user = $request->user();

        $activity->record('account.exported', 'A customer downloaded their data.', $user);

        $filename = 'tandoor-house-my-data-'.now()->format('Y-m-d').'.json';

        return response()->streamDownload(function () use ($export, $user) {
            echo '{';
            $first = true;

            foreach ($export->sections($user) as $name => $value) {
                echo ($first ? '' : ',').json_encode($name).':';
                $first = false;

                if ($value instanceof \Generator) {
                    echo '[';
                    $n = 0;
                    foreach ($value as $row) {
                        echo ($n++ ? ',' : '').json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        flush();
                    }
                    echo ']';
                } else {
                    echo json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
            }

            echo '}';
        }, $filename, [
            'Content-Type' => 'application/json; charset=utf-8',
            'Cache-Control' => 'no-store, private',
        ]);
    }
}
