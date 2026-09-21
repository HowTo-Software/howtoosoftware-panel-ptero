@extends('layouts.admin')

@section('title')
    {{ __('Administration') }}
@endsection

@section('content-header')
    <h1>{{ __('Administrative Overview') }}<small>{{ __('A quick glance at your system.') }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">{{ __('Admin') }}</a></li>
        <li class="active">{{ __('Overview') }}</li>
    </ol>
@endsection

@section('content')
@php
    $barClasses = ['ok' => 'progress-bar-success', 'warning' => 'progress-bar-warning', 'critical' => 'progress-bar-danger', 'unknown' => ''];
    $labelClasses = ['ok' => 'label-success', 'warning' => 'label-warning', 'critical' => 'label-danger', 'unknown' => 'label-default'];
@endphp
<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary" id="system-health">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-heartbeat"></i> {{ __('Panel Host Health') }}</h3>
                <div class="box-tools pull-right">
                    <span class="text-muted small" data-health-updated></span>
                    <button type="button" class="btn btn-box-tool" data-health-refresh title="{{ __('Refresh now') }}"><i class="fa fa-refresh"></i></button>
                </div>
            </div>
            <div class="box-body">
                <div class="row">
                    @foreach ([
                        'cpu' => ['label' => __('CPU'), 'icon' => 'fa-microchip'],
                        'memory' => ['label' => __('Memory'), 'icon' => 'fa-tachometer'],
                        'storage' => ['label' => __('Storage'), 'icon' => 'fa-hdd-o'],
                    ] as $metric => $meta)
                        <div class="col-sm-4" data-health-metric="{{ $metric }}">
                            <div style="margin-bottom:10px;">
                                <strong><i class="fa fa-fw {{ $meta['icon'] }}"></i> {{ $meta['label'] }}</strong>
                                <span class="label label-default pull-right" data-health-status>{{ __('Checking') }}</span>
                            </div>
                            <div class="progress progress-sm">
                                <div class="progress-bar" data-health-bar style="width: 0;"></div>
                            </div>
                            <div>
                                <span class="h4" style="margin:0;" data-health-percent>&mdash;</span>
                                <span class="text-muted small" data-health-detail></span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="box-footer text-muted small">
                <i class="fa fa-fw fa-clock-o"></i> <span data-health-uptime>&mdash;</span>
            </div>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-xs-12">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-sitemap"></i> {{ __('Resource Consumption by Node') }}</h3>
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>{{ __('Node') }}</th>
                            <th class="text-center" style="width: 90px;">{{ __('Servers') }}</th>
                            <th style="width: 35%;">{{ __('Memory') }}</th>
                            <th style="width: 35%;">{{ __('Disk') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($nodes as $node)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.nodes.view', $node['id']) }}">{{ $node['name'] }}</a>
                                    @if ($node['maintenance'])
                                        <span class="label label-warning">{{ __('Maintenance') }}</span>
                                    @endif
                                </td>
                                <td class="text-center">{{ $node['servers'] }}</td>
                                @foreach (['memory', 'disk'] as $resource)
                                    @php($usage = $node[$resource])
                                    <td>
                                        <div class="progress progress-xs" style="margin-bottom:4px;">
                                            <div class="progress-bar {{ $barClasses[$usage['status']] }}" style="width: {{ min(100, $usage['percent'] ?? 0) }}%;"></div>
                                        </div>
                                        <span class="text-muted small">
                                            {{ number_format($usage['used_mib']) }} / {{ number_format($usage['total_mib']) }} MiB
                                            <span class="label {{ $labelClasses[$usage['status']] }}">{{ $usage['percent'] === null ? '—' : $usage['percent'] . '%' }}</span>
                                        </span>
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted">{{ __('No nodes have been configured yet.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-xs-12">
        <div class="box
            @if($version->isLatestPanel())
                box-success
            @else
                box-danger
            @endif
        ">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('System Information') }}</h3>
            </div>
            <div class="box-body">
                @if ($version->isLatestPanel())
                    {!! __('You are running Pterodactyl Panel version :version. Your panel is up-to-date!', ['version' => '<code>' . e(config('app.version')) . '</code>']) !!}
                @else
                    Your panel is <strong>not up-to-date!</strong> The latest version is <a href="https://github.com/Pterodactyl/Panel/releases/v{{ $version->getPanel() }}" target="_blank"><code>{{ $version->getPanel() }}</code></a> and you are currently running version <code>{{ config('app.version') }}</code>. You can find instructions on how to update your panel <a href="https://pterodactyl.io/panel/1.0/updating.html">here</a>.
                @endif
            </div>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-xs-6 col-sm-3 text-center">
        <a href="{{ $version->getDiscord() }}"><button class="btn btn-warning" style="width:100%;"><i class="fa fa-fw fa-support"></i> {{ __('Get Help') }} <small>{{ __('(via Discord)') }}</small></button></a>
    </div>
    <div class="col-xs-6 col-sm-3 text-center">
        <a href="https://pterodactyl.io"><button class="btn btn-primary" style="width:100%;"><i class="fa fa-fw fa-link"></i> {{ __('Documentation') }}</button></a>
    </div>
    <div class="clearfix visible-xs-block">&nbsp;</div>
    <div class="col-xs-6 col-sm-3 text-center">
        <a href="https://github.com/pterodactyl/panel"><button class="btn btn-primary" style="width:100%;"><i class="fa fa-fw fa-support"></i> GitHub</button></a>
    </div>
    <div class="col-xs-6 col-sm-3 text-center">
        <a href="{{ $version->getDonations() }}"><button class="btn btn-success" style="width:100%;"><i class="fa fa-fw fa-money"></i> {{ __('Support the Project') }}</button></a>
    </div>
</div>
@endsection

@section('footer-scripts')
    @parent
    <script>
        (function () {
            var endpoint = @json(route('admin.health'));
            var statuses = {
                ok: { label: @json(__('Healthy')), labelClass: 'label-success', barClass: 'progress-bar-success' },
                warning: { label: @json(__('Elevated')), labelClass: 'label-warning', barClass: 'progress-bar-warning' },
                critical: { label: @json(__('Critical')), labelClass: 'label-danger', barClass: 'progress-bar-danger' },
                unknown: { label: @json(__('Unavailable')), labelClass: 'label-default', barClass: '' }
            };
            var strings = {
                cores: @json(__('cores')),
                load: @json(__('load')),
                of: @json(__('of')),
                uptime: @json(__('Host uptime: :uptime')),
                updated: @json(__('Updated :time')),
                unavailable: @json(__('Not available on this host.'))
            };

            function bytes(value) {
                if (typeof value !== 'number') {
                    return '\u2014';
                }

                var units = ['B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB'];
                var index = 0;
                while (value >= 1024 && index < units.length - 1) {
                    value /= 1024;
                    index++;
                }

                return (index === 0 ? value : value.toFixed(value >= 100 ? 0 : 1)) + ' ' + units[index];
            }

            function duration(seconds) {
                if (typeof seconds !== 'number') {
                    return null;
                }

                var days = Math.floor(seconds / 86400);
                var hours = Math.floor((seconds % 86400) / 3600);

                return (days > 0 ? days + 'd ' : '') + (days > 0 || hours > 0 ? hours + 'h ' : '') + Math.floor((seconds % 3600) / 60) + 'm';
            }

            function detail(metric, data) {
                if (metric === 'cpu') {
                    var parts = [data.cores + ' ' + strings.cores];
                    if (data.load) {
                        parts.push(strings.load + ' ' + data.load.join(' / '));
                    }

                    return parts.join(' \u00b7 ');
                }

                return typeof data.used === 'number'
                    ? bytes(data.used) + ' ' + strings.of + ' ' + bytes(data.total)
                    : strings.unavailable;
            }

            function render(payload) {
                ['cpu', 'memory', 'storage'].forEach(function (metric) {
                    var container = document.querySelector('[data-health-metric="' + metric + '"]');
                    if (!container) {
                        return;
                    }

                    var data = payload[metric] || { status: 'unknown', percent: null };
                    var status = statuses[data.status] || statuses.unknown;
                    var percent = typeof data.percent === 'number' ? data.percent : null;

                    var badge = container.querySelector('[data-health-status]');
                    badge.className = 'label pull-right ' + status.labelClass;
                    badge.textContent = status.label;

                    var bar = container.querySelector('[data-health-bar]');
                    bar.className = 'progress-bar ' + status.barClass;
                    bar.style.width = Math.min(100, percent === null ? 0 : percent) + '%';

                    container.querySelector('[data-health-percent]').textContent = percent === null ? '\u2014' : percent + '%';
                    container.querySelector('[data-health-detail]').textContent = detail(metric, data);
                });

                var uptime = duration(payload.uptime_seconds);
                document.querySelector('[data-health-uptime]').textContent = uptime === null
                    ? strings.unavailable
                    : strings.uptime.replace(':uptime', uptime);
                document.querySelector('[data-health-updated]').textContent = strings.updated
                    .replace(':time', new Date(payload.generated_at).toLocaleTimeString());
            }

            function poll() {
                $.getJSON(endpoint).done(render);
            }

            render(@json($health));
            document.querySelector('[data-health-refresh]').addEventListener('click', poll);
            window.setInterval(poll, 15000);
        })();
    </script>
@endsection
