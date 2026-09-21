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
                <h3 class="box-title"><i class="fa fa-sitemap"></i> {{ __('Live Consumption by Node') }}</h3>
                <div class="box-tools pull-right">
                    <span class="text-muted small" data-nodes-updated></span>
                </div>
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>{{ __('Node') }}</th>
                            <th class="text-center" style="width: 110px;">{{ __('Running') }}</th>
                            <th style="width: 25%;">{{ __('CPU') }}</th>
                            <th style="width: 25%;">{{ __('Memory') }}</th>
                            <th style="width: 25%;">{{ __('Disk') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($nodes as $node)
                            <tr data-node-row="{{ $node['id'] }}">
                                <td>
                                    <a href="{{ route('admin.nodes.view', $node['id']) }}">{{ $node['name'] }}</a>
                                    @if ($node['maintenance'])
                                        <span class="label label-warning">{{ __('Maintenance') }}</span>
                                    @endif
                                    <span class="label label-danger" data-node-unreachable hidden>{{ __('Unreachable') }}</span>
                                </td>
                                <td class="text-center"><span data-node-online>&mdash;</span> / {{ $node['servers'] }}</td>
                                @foreach (['cpu', 'memory', 'disk'] as $resource)
                                    <td data-node-metric="{{ $resource }}">
                                        <div class="progress progress-xs" style="margin-bottom:4px;">
                                            <div class="progress-bar" data-node-bar style="width: 0;"></div>
                                        </div>
                                        <span class="text-muted small" data-node-text>&mdash;</span>
                                        <span class="label label-default" data-node-percent>&mdash;</span>
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted">{{ __('No nodes have been configured yet.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="box-footer text-muted small">
                {{ __('CPU and memory are measured against the totals the daemon reports for its host. Wings reports disk usage but no filesystem total, so disk is measured against the capacity configured on the node.') }}
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
            var hostEndpoint = @json(route('admin.health'));
            var nodesEndpoint = @json(route('admin.health.nodes'));
            var HOST_INTERVAL = 3000;
            var NODE_INTERVAL = 5000;
            var statuses = {
                ok: { label: @json(__('Healthy')), labelClass: 'label-success', barClass: 'progress-bar-success' },
                warning: { label: @json(__('Elevated')), labelClass: 'label-warning', barClass: 'progress-bar-warning' },
                critical: { label: @json(__('Critical')), labelClass: 'label-danger', barClass: 'progress-bar-danger' },
                unlimited: { label: @json(__('Unlimited')), labelClass: 'label-info', barClass: '' },
                unknown: { label: @json(__('Unavailable')), labelClass: 'label-default', barClass: '' }
            };
            var strings = {
                cores: @json(__('cores')),
                threads: @json(__('threads')),
                load: @json(__('load')),
                of: @json(__('of')),
                uptime: @json(__('Host uptime: :uptime')),
                updated: @json(__('Updated :time')),
                unavailable: @json(__('Not available on this host.')),
                unreachable: @json(__('Daemon unreachable'))
            };
            var sources = {
                daemon: @json(__('Total reported by the daemon for its host.')),
                configured: @json(__('Capacity configured on the node. The daemon does not report a total for this resource.'))
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
                stamp('[data-health-updated]', payload.generated_at);
            }

            function nodeText(metric, data) {
                if (typeof data.used !== 'number') {
                    return strings.unreachable;
                }

                if (data.status === 'unknown') {
                    return statuses.unknown.label;
                }

                // A null total means the resource is uncapped, so only the usage is meaningful.
                if (metric === 'cpu') {
                    var used = (data.used / 100).toFixed(2);

                    return data.total === null
                        ? used + ' ' + strings.threads
                        : used + ' ' + strings.of + ' ' + (data.total / 100) + ' ' + strings.threads;
                }

                return data.total === null
                    ? bytes(data.used)
                    : bytes(data.used) + ' ' + strings.of + ' ' + bytes(data.total);
            }

            function renderNodes(payload) {
                payload.nodes.forEach(function (node) {
                    var row = document.querySelector('[data-node-row="' + node.id + '"]');
                    if (!row) {
                        return;
                    }

                    row.querySelector('[data-node-unreachable]').hidden = node.reachable;
                    row.querySelector('[data-node-online]').textContent = node.reachable ? node.online : '\u2014';

                    ['cpu', 'memory', 'disk'].forEach(function (metric) {
                        var cell = row.querySelector('[data-node-metric="' + metric + '"]');
                        var data = node[metric] || { status: 'unknown', percent: null };
                        var status = statuses[data.status] || statuses.unknown;
                        var percent = typeof data.percent === 'number' ? data.percent : null;

                        var bar = cell.querySelector('[data-node-bar]');
                        bar.className = 'progress-bar ' + status.barClass;
                        bar.style.width = Math.min(100, percent === null ? 0 : percent) + '%';

                        var badge = cell.querySelector('[data-node-percent]');
                        badge.className = 'label ' + status.labelClass;
                        badge.textContent = percent === null ? status.label : percent + '%';

                        var text = cell.querySelector('[data-node-text]');
                        text.textContent = nodeText(metric, data);
                        text.title = sources[data.source] || '';
                    });
                });

                stamp('[data-nodes-updated]', payload.generated_at);
            }

            function stamp(selector, generatedAt) {
                document.querySelector(selector).textContent = strings.updated
                    .replace(':time', new Date(generatedAt).toLocaleTimeString());
            }

            // Skips a tick rather than stacking requests when a daemon is slow to answer.
            function poller(url, handler, interval) {
                var inFlight = false;
                var run = function () {
                    if (inFlight) {
                        return;
                    }

                    inFlight = true;
                    $.getJSON(url).done(handler).always(function () {
                        inFlight = false;
                    });
                };

                window.setInterval(run, interval);

                return run;
            }

            render(@json($health));
            var pollHost = poller(hostEndpoint, render, HOST_INTERVAL);
            var pollNodes = poller(nodesEndpoint, renderNodes, NODE_INTERVAL);

            document.querySelector('[data-health-refresh]').addEventListener('click', function () {
                pollHost();
                pollNodes();
            });

            pollNodes();
        })();
    </script>
@endsection
