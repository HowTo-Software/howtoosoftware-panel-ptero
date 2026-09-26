@extends('layouts.admin')

@section('title')
    {{ __('Server —') }} {{ $server->name }}: {{ __('Delete') }}
@endsection

@section('content-header')
    <h1>{{ $server->name }}<small>{{ __('Delete this server from the panel.') }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">{{ __('Admin') }}</a></li>
        <li><a href="{{ route('admin.servers') }}">{{ __('Servers') }}</a></li>
        <li><a href="{{ route('admin.servers.view', $server->id) }}">{{ $server->name }}</a></li>
        <li class="active">{{ __('Delete') }}</li>
    </ol>
@endsection

@section('content')
@include('admin.servers.partials.navigation')
<div class="row">
    <div class="col-md-6">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('Safely Delete Server') }}</h3>
            </div>
            <div class="box-body">
                <p>{{ __('This action will attempt to delete the server from both the panel and daemon. If either one reports an error the action will be cancelled.') }}</p>
                <p class="text-danger small">{{ __('Deleting a server is an irreversible action.') }} <strong>{{ __('All server data') }}</strong> {{ __('(including files and users) will be removed from the system.') }}</p>
            </div>
            <div class="box-footer">
                <form id="deleteform" action="{{ route('admin.servers.view.delete', $server->id) }}" method="POST">
                    {!! csrf_field() !!}
                    <button id="deletebtn" class="btn btn-danger">{{ __('Safely Delete This Server') }}</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="box box-danger">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('Force Delete Server') }}</h3>
            </div>
            <div class="box-body">
                <p>{{ __('This action will attempt to delete the server from both the panel and daemon. If the daemon does not respond, or reports an error the deletion will continue.') }}</p>
                <p class="text-danger small">{{ __('Deleting a server is an irreversible action.') }} <strong>{{ __('All server data') }}</strong> {{ __('(including files and users) will be removed from the system. This method may leave dangling files on your daemon if it reports an error.') }}</p>
            </div>
            <div class="box-footer">
                <form id="forcedeleteform" action="{{ route('admin.servers.view.delete', $server->id) }}" method="POST">
                    {!! csrf_field() !!}
                    <input type="hidden" name="force_delete" value="1" />
                    <button id="forcedeletebtn"" class="btn btn-danger">{{ __('Forcibly Delete This Server') }}</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('footer-scripts')
    @parent
    <script>
    $('#deletebtn').click(function (event) {
        event.preventDefault();
        swal({
            title: '',
            type: 'warning',
            text: @json(__('Are you sure that you want to delete this server? There is no going back, all data will immediately be removed.')),
            showCancelButton: true,
            confirmButtonText: @json(__('Delete')),
            confirmButtonColor: '#d9534f',
            closeOnConfirm: false
        }, function () {
            $('#deleteform').submit()
        });
    });

    $('#forcedeletebtn').click(function (event) {
        event.preventDefault();
        swal({
            title: '',
            type: 'warning',
            text: @json(__('Are you sure that you want to delete this server? There is no going back, all data will immediately be removed.')),
            showCancelButton: true,
            confirmButtonText: @json(__('Delete')),
            confirmButtonColor: '#d9534f',
            closeOnConfirm: false
        }, function () {
            $('#forcedeleteform').submit()
        });
    });
    </script>
@endsection
