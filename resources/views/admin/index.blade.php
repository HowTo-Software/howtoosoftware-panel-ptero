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
