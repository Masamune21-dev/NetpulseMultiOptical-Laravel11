<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<style>
    @page { margin: 26px 30px 46px; }
    * { box-sizing: border-box; }
    body { font-family: DejaVu Sans, sans-serif; color: #111; font-size: 10px; margin: 0; }

    .head { background: #0b0b0b; color: #fff; padding: 14px 16px; border-bottom: 4px solid #ffe14a; }
    .head h1 { margin: 0; font-size: 16px; }
    .head .brand { color: #ffe14a; }
    .head .iface { margin-top: 4px; font-size: 12px; font-weight: bold; }
    .head .dev { margin-top: 1px; font-size: 9.5px; color: #cfcfcf; }

    .meta { width: 100%; margin: 10px 0 4px; }
    .meta td { font-size: 9.5px; color: #444; }
    .meta b { color: #111; }

    .cards { width: 100%; border-collapse: separate; border-spacing: 7px 0; margin: 8px 0 14px; }
    .cards td { width: 25%; border: 1.5px solid #111; border-radius: 6px; padding: 8px 10px; }
    .cards .k { font-size: 7.5px; font-weight: bold; text-transform: uppercase; color: #666; }
    .cards .v { font-size: 15px; font-weight: bold; margin-top: 3px; }
    .c1 { background: #f4feff; }
    .c2 { background: #fff5f5; }
    .c3 { background: #fffdf0; }
    .c4 { background: #f6fff4; }
    .ok { color: #0a8f3c; } .warn { color: #b96b00; } .bad { color: #d11; }

    table.report { width: 100%; border-collapse: collapse; }
    table.report thead th {
        background: #111; color: #fff; font-size: 8.5px; text-transform: uppercase;
        padding: 6px 8px; text-align: left; border: 1px solid #111;
    }
    table.report tbody td { padding: 5px 8px; border: 1px solid #ddd; font-size: 9px; }
    table.report tbody tr:nth-child(even) td { background: #fafafa; }
    .num { text-align: center; }
    .badge-down { background: #d11; color: #fff; font-size: 7.5px; font-weight: bold; padding: 1px 5px; border-radius: 3px; }
    .empty { padding: 18px; text-align: center; color: #777; }

    .foot { position: fixed; bottom: -32px; left: 0; right: 0; font-size: 8px; color: #888;
        border-top: 1px solid #ddd; padding-top: 4px; }
    .foot .pg:after { content: counter(page) " / " counter(pages); }
</style>
</head>
<body>
    <div class="head">
        <h1><span class="brand">Net</span>Pulse — Interface Downtime Report</h1>
        <div class="iface">{{ $meta['if_alias'] ?: $meta['if_name'] }}@if($summary['still_down']) <span class="badge-down">DOWN NOW</span>@endif</div>
        <div class="dev">{{ $meta['device_name'] }} · {{ $meta['if_name'] }}</div>
    </div>

    <table class="meta">
        <tr>
            <td>Generated: <b>{{ $generatedAt }}</b></td>
            <td style="text-align:right">Period: <b>last {{ $days }} days</b></td>
        </tr>
    </table>

    <table class="cards">
        <tr>
            <td class="c1"><div class="k">Down count</div><div class="v">{{ $summary['down_count'] }}</div></td>
            <td class="c2"><div class="k">Total downtime</div><div class="v">{{ $totalDowntime }}</div></td>
            <td class="c3"><div class="k">Longest down</div><div class="v">{{ $longest }}</div></td>
            <td class="c4"><div class="k">Availability</div><div class="v {{ $availClass }}">{{ $summary['availability'] !== null ? number_format($summary['availability'], 3) . '%' : '—' }}</div></td>
        </tr>
    </table>

    <table class="report">
        <thead>
            <tr>
                <th class="num" style="width:8%">#</th>
                <th style="width:30%">Down at</th>
                <th style="width:30%">Up at</th>
                <th style="width:20%">Duration</th>
            </tr>
        </thead>
        <tbody>
            @forelse($events as $i => $e)
            <tr>
                <td class="num">{{ $i + 1 }}</td>
                <td>{{ $e['down_at'] }}</td>
                <td>@if($e['ongoing'])<span class="badge-down">still down</span>@else{{ $e['up_at'] }}@endif</td>
                <td>{{ $e['duration_h'] }}</td>
            </tr>
            @empty
            <tr><td colspan="4" class="empty">No down events in this period.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="foot">
        <span class="pg" style="float:right"></span>
        NetPulse SLA Report · netpulse.bmkv.net
    </div>
</body>
</html>
