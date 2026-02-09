@extends('layouts.app')

@section('content')
    <style>
        .topbar form {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .topbar label {
            font-size: 14px;
            font-weight: 600;
            color: #444;
        }

        .topbar select {
            min-width: 160px;
            padding: 8px 32px 8px 12px;
            border-radius: 6px;
            border: 1px solid #ccc;
            background: white;
            color: #333;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 14px;
        }

        .topbar select:hover {
            border-color: #888;
            box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.1);
        }

        .topbar select:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
        }

        .topbar select option:checked {
            background: #007bff;
            color: white;
        }

        @media (max-width: 768px) {
            .topbar form {
                width: 100%;
                gap: 6px;
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                align-items: center;
            }

            .topbar label {
                font-size: 12px;
                margin-bottom: 2px;
            }

            .topbar select {
                min-width: 0;
                width: 100%;
                font-size: 13px;
                padding: 6px 28px 6px 10px;
            }
        }
    </style>

    <div class="topbar">
        <h1>
            <i class="fas fa-server"></i>
            OLT Monitor
        </h1>

        @if (!empty($olts) && $oltId)
            <form method="get" class="topbar-filters">
                <label><strong>OLT</strong></label>
                <select name="olt" onchange="this.form.submit()">
                    @foreach ($olts as $id => $olt)
                        <option value="{{ $id }}" @if ($id === $oltId) selected @endif>
                            {{ $olt['name'] ?? $id }}
                        </option>
                    @endforeach
                </select>

                <label><strong>PON</strong></label>
                <select name="pon" onchange="this.form.submit()">
                    @foreach (($olts[$oltId]['pons'] ?? []) as $p)
                        <option value="{{ $p }}" @if ($p === $pon) selected @endif>
                            {{ $p }}
                        </option>
                    @endforeach
                </select>
            </form>
        @endif
    </div>

    @if (empty($olts) || !$oltId)
        <div class="alert warning">
            <i class="fas fa-triangle-exclamation"></i>
            No OLT configured. Please fill `config/olt.php`.
        </div>
    @else
        <div style="margin-bottom:10px;">
            <b>OLT:</b> {{ $olts[$oltId]['name'] ?? $oltId }} |
            <b>PON:</b> {{ $pon }} |
            <b>Total ONU:</b> {{ (int) ($data['total'] ?? 0) }} |
            <b>Last Update:</b> {{ $lastUpdate }}
        </div>
    @endif

    @if (!empty($olts) && $oltId)
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th width="90">ONU ID</th>
                        <th>Nama</th>
                        <th width="130">MAC</th>
                        <th width="70">Status</th>
                        <th width="90">RX (dBm)</th>
                        <th width="90">TX (dBm)</th>
                        <th width="90">Temp (°C)</th>
                        <th width="90">Signal</th>
                        <th width="120">Uptime</th>
                    </tr>
                </thead>
                <tbody>
                    @if (!empty($data['onu']))
                        @foreach ($data['onu'] as $onu)
                            <tr>
                                <td>{{ $onu['onu_id'] }}</td>
                                <td>{{ $onu['name'] ?? '-' }}</td>
                                <td>{{ $onu['mac'] ?? '-' }}</td>
                                <td>
                                    @if (($onu['status'] ?? '') === 'Up')
                                        <span style="color:green;font-weight:bold;">Up</span>
                                    @else
                                        <span style="color:red;font-weight:bold;">Down</span>
                                    @endif
                                </td>
                                <td>{{ $onu['rx_power'] ?? '-' }}</td>
                                <td>{{ $onu['tx_power'] ?? '-' }}</td>
                                <td>{{ $onu['temperature'] ?? '-' }}</td>
                                <td>
                                    @php
                                        $signal = $onu['signal'] ?? '-';
                                        $color = match ($signal) {
                                            'good' => 'green',
                                            'warning' => 'orange',
                                            'critical' => 'red',
                                            'offline' => '#64748b',
                                            default => 'gray',
                                        };
                                    @endphp
                                    <span style="color:{{ $color }}; font-weight:bold;">
                                        {{ ucfirst($signal) }}
                                    </span>
                                </td>
                                <td>{{ $onu['uptime'] ?? '-' }}</td>
                            </tr>
                        @endforeach
                    @else
                        <tr>
                            <td colspan="9" align="center">
                                Data ONU belum tersedia
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    @endif
@endsection

@push('scripts')
    <script>
        // OLT page is server-rendered; reload periodically so latest poll result is visible.
        if (window.netpulseRefresh && typeof window.netpulseRefresh.register === 'function') {
            window.netpulseRefresh.register('olt', () => {
                if (!location.pathname.startsWith('/olt')) return;
                if (document.hidden) return;
                location.reload();
            }, { minIntervalMs: 60000 });
        } else {
            setTimeout(() => location.reload(), 60000);
        }
    </script>
@endpush
