<?php

namespace App\Http\Controllers\Staff\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\Reports\ReportRequest;
use App\Reports\ReportCsv;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportCsvController extends Controller
{
    public function __invoke(ReportRequest $request, string $section): StreamedResponse
    {
        return ReportCsv::download($section, $request->range());
    }
}
