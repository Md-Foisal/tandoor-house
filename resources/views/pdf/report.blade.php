@php
    $numeric = ['money', 'int', 'percent', 'change', 'minutes', 'auto'];
@endphp
<!doctype html>
<html lang="en-GB">
<head>
    <meta charset="utf-8">
    <title>{{ $restaurant['name'] }} · {{ __('staff_reports.pdf.title') }}</title>
    <style>
        @page { margin: 36px 40px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1d1a17; }
        h1 { font-size: 20px; margin: 0 0 2px; }
        h2 { font-size: 14px; margin: 22px 0 4px; padding-bottom: 3px; border-bottom: 2px solid #1d1a17; }
        h3 { font-size: 11px; margin: 14px 0 4px; }
        .muted { color: #6b645c; }
        .note { color: #6b645c; font-size: 9px; margin: 3px 0 0; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th { text-align: left; font-size: 8px; text-transform: uppercase; letter-spacing: .05em; color: #6b645c; border-bottom: 1px solid #1d1a17; padding: 4px 4px; }
        table.data td { padding: 4px; border-bottom: 1px solid #ddd6cc; }
        table.data .num { text-align: right; }
        table.data tr.totals td { border-bottom: 0; border-top: 1px solid #1d1a17; font-weight: bold; }
        .head { width: 100%; margin-bottom: 6px; }
        .head td { vertical-align: top; padding: 0; }
        .right { text-align: right; }
    </style>
</head>
<body>
    <table class="head">
        <tr>
            <td>
                <h1>{{ $restaurant['name'] }}</h1>
                <div>{{ __('staff_reports.pdf.title') }}</div>
                <div class="muted">{{ $period }} · {{ $channel }}</div>
            </td>
            <td class="right muted">
                {{ $restaurant['company']['legal_name'] }}<br>
                {{ __('staff_reports.pdf.generated', ['when' => $generated]) }}
            </td>
        </tr>
    </table>

    @foreach ($sections as $section)
        <h2>{{ $section['title'] }}</h2>

        @foreach ($section['tables'] as $table)
            @if (count($section['tables']) > 1)
                <h3>{{ $table['title'] }}</h3>
            @endif
            <table class="data">
                <thead>
                    <tr>
                        @foreach ($table['columns'] as $column)
                            <th @class(['num' => in_array($column['type'], $numeric, true)])>{{ $column['label'] }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($table['rows'] as $row)
                        <tr>
                            @foreach ($table['columns'] as $column)
                                <td @class(['num' => in_array($column['type'], $numeric, true)])>{{ $format::cell($row[$column['key']] ?? null, $column['type'], $row) }}</td>
                            @endforeach
                        </tr>
                    @empty
                        <tr><td colspan="{{ count($table['columns']) }}" class="muted">{{ __('staff_reports.empty.title') }}</td></tr>
                    @endforelse
                    @if ($table['totals'])
                        <tr class="totals">
                            @foreach ($table['columns'] as $column)
                                <td @class(['num' => in_array($column['type'], $numeric, true)])>{{ $format::cell($table['totals'][$column['key']] ?? null, $column['type'], $table['totals']) }}</td>
                            @endforeach
                        </tr>
                    @endif
                </tbody>
            </table>
            @if ($table['note'])
                <p class="note">{{ $table['note'] }}</p>
            @endif
        @endforeach
    @endforeach
</body>
</html>
