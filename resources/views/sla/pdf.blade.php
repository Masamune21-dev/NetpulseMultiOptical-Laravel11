<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<style>
    @page { margin: 26px 28px 46px; }
    * { box-sizing: border-box; }
    body { font-family: DejaVu Sans, sans-serif; color: #111; font-size: 10px; margin: 0; }

    .head { background: #0b0b0b; color: #fff; padding: 14px 16px; border-bottom: 4px solid #ffe14a; }
    .head h1 { margin: 0; font-size: 17px; letter-spacing: .5px; }
    .head .brand { color: #ffe14a; }
    .head .sub { margin-top: 3px; font-size: 9.5px; color: #cfcfcf; }

    .meta { width: 100%; margin: 12px 0 6px; border-collapse: collapse; }
    .meta td { font-size: 9.5px; color: #444; padding: 1px 0; }
    .meta b { color: #111; }

    .cards { width: 100%; border-collapse: separate; border-spacing: 8px 0; margin: 8px 0 14px; }
    .cards td { width: 33.33%; border: 1.5px solid #111; border-radius: 6px; padding: 9px 11px; }
    .cards .k { font-size: 8px; font-weight: bold; text-transform: uppercase; color: #666; letter-spacing: .4px; }
    .cards .v { font-size: 17px; font-weight: bold; color: #111; margin-top: 3px; }
    .c1 { box-shadow: 3px 3px 0 #00d1ff; background: #f4feff; }
    .c2 { background: #fffdf0; }
    .c3 { background: #fff5f5; }

    table.report { width: 100%; border-collapse: collapse; }
    table.report thead th {
        background: #111; color: #fff; font-size: 8.5px; text-transform: uppercase;
        letter-spacing: .3px; padding: 6px 7px; text-align: left; border: 1px solid #111;
    }
    table.report tbody td { padding: 5px 7px; border: 1px solid #ddd; font-size: 9px; }
    table.report tbody tr:nth-child(even) td { background: #fafafa; }
    .ifname { font-weight: bold; }
    .ifalias { color: #555; font-size: 8px; }
    .num { text-align: center; }
    .ok { color: #0a8f3c; font-weight: bold; }
    .warn { color: #b96b00; font-weight: bold; }
    .bad { color: #d11; font-weight: bold; }
    .badge-down { background: #d11; color: #fff; font-size: 7.5px; font-weight: bold;
        padding: 1px 5px; border-radius: 3px; }

    .foot { position: fixed; bottom: -32px; left: 0; right: 0; font-size: 8px; color: #888;
        border-top: 1px solid #ddd; padding-top: 4px; }
    .foot .pg:after { content: counter(page) " / " counter(pages); }
    .empty { padding: 18px; text-align: center; color: #777; }
</style>
</head>
<body>
    <div class="head">
        <h1><span class="brand">Net</span>Pulse — SLA / Uptime Report</h1>
        <div class="sub">Interface downtime over the last {{ $days }} days</div>
    </div>

    <table class="meta">
        <tr>
            <td>Generated: <b>{{ $generatedAt }}</b></td>
            <td style="text-align:right">Scope: <b>{{ $scope }}</b></td>
        </tr>
        @if($search !== '')
        <tr><td colspan="2">Filter: <b>{{ $search }}</b></td></tr>
        @endif
    </table>

    <table class="cards">
        <tr>
            <td class="c1"><div class="k">Interfaces with downs</div><div class="v">{{ $totals['interfaces'] ?? 0 }}</div></td>
            <td class="c2"><div class="k">Total down events</div><div class="v">{{ $totals['down_events'] ?? 0 }}</div></td>
            <td class="c3"><div class="k">Total downtime</div><div class="v">{{ $totalDowntime }}</div></td>
        </tr>
    </table>

    <table class="report">
        <thead>
            <tr>
                <th style="width:7%">#</th>
                <th style="width:17%">Device</th>
                <th style="width:25%">Interface</th>
                <th class="num" style="width:9%">Downs</th>
                <th class="num" style="width:14%">Downtime</th>
                <th class="num" style="width:12%">Avail %</th>
                <th style="width:16%">Last down</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $i => $r)
            <tr>
                <td class="num">{{ $i + 1 }}</td>
                <td>{{ $r['device_name'] }}</td>
                <td>
                    <span class="ifname">{{ $r['if_alias'] ?: $r['if_name'] }}</span>
                    @if($r['still_down']) <span class="badge-down">DOWN</span>@endif
                    @if($r['if_alias'])<div class="ifalias">{{ $r['if_name'] }}</div>@endif
                </td>
                <td class="num">{{ $r['down_count'] }}</td>
                <td class="num">{{ $r['downtime_h'] }}</td>
                <td class="num {{ $r['avail_class'] }}">{{ $r['avail_str'] }}</td>
                <td>{{ $r['last_down_at'] }}</td>
            </tr>
            @empty
            <tr><td colspan="7" class="empty">No interface downs in this period.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="foot">
        <span class="pg" style="float:right"></span>
        NetPulse SLA Report · netpulse.bmkv.net
    </div>
</body>
</html>
