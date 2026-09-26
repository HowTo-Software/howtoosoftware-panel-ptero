@extends('layouts.admin')

@section('title')
    {{ $node->name }}{{ __(': Configuration') }}
@endsection

@section('content-header')
    <h1>{{ $node->name }}<small>{{ __('Your daemon configuration file.') }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">{{ __('Admin') }}</a></li>
        <li><a href="{{ route('admin.nodes') }}">{{ __('Nodes') }}</a></li>
        <li><a href="{{ route('admin.nodes.view', $node->id) }}">{{ $node->name }}</a></li>
        <li class="active">{{ __('Configuration') }}</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-xs-12">
        <div class="nav-tabs-custom nav-tabs-floating">
            <ul class="nav nav-tabs">
                <li><a href="{{ route('admin.nodes.view', $node->id) }}">{{ __('About') }}</a></li>
                <li><a href="{{ route('admin.nodes.view.settings', $node->id) }}">{{ __('Settings') }}</a></li>
                <li class="active"><a href="{{ route('admin.nodes.view.configuration', $node->id) }}">{{ __('Configuration') }}</a></li>
                <li><a href="{{ route('admin.nodes.view.allocation', $node->id) }}">{{ __('Allocation') }}</a></li>
                <li><a href="{{ route('admin.nodes.view.servers', $node->id) }}">{{ __('Servers') }}</a></li>
            </ul>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-sm-8">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('Configuration File') }}</h3>
            </div>
            <div class="box-body">
                <pre class="no-margin">{{ $node->getYamlConfiguration() }}</pre>
            </div>
            <div class="box-footer">
                <p class="no-margin">{{ __('This file should be placed in your daemon\'s root directory (usually') }} <code>/etc/pterodactyl</code>{{ __(') in a file called') }} <code>config.yml</code>.</p>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="box box-success">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('Auto-Deploy') }}</h3>
            </div>
            <div class="box-body">
                <p class="text-muted small">
                    {{ __('Use the button below to generate a custom deployment command that can be used to configure') }}
                    {{ __('wings on the target server with a single command.') }}
                </p>
            </div>
            <div class="box-footer">
                <button type="button" id="configTokenBtn" class="btn btn-sm btn-default" style="width:100%;">{{ __('Generate Token') }}</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('footer-scripts')
    @parent
    <script>
    $('#configTokenBtn').on('click', function (event) {
        $.ajax({
            method: 'POST',
            url: '{{ route('admin.nodes.view.configuration.token', $node->id) }}',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        }).done(function (data) {
            swal({
                type: 'success',
            title: @json(__('Token created.')),
            text: '<p>' + @json(__('To auto-configure your node run the following command:')) + '<br /><small><pre>cd /etc/pterodactyl && sudo wings configure --panel-url {{ config('app.url') }} --token ' + data.token + ' --node ' + data.node + '{{ config('app.debug') ? ' --allow-insecure' : '' }}</pre></small></p>',
                html: true
            })
        }).fail(function () {
            swal({
            title: @json(__('Error')),
            text: @json(__('Something went wrong creating your token.')),
                type: 'error'
            });
        });
    });
    </script>
@endsection
