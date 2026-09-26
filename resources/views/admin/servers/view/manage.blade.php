@extends('layouts.admin')

@section('title')
    {{ __('Server —') }} {{ $server->name }}: {{ __('Manage') }}
@endsection

@section('content-header')
    <h1>{{ $server->name }}<small>{{ __('Additional actions to control this server.') }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">{{ __('Admin') }}</a></li>
        <li><a href="{{ route('admin.servers') }}">{{ __('Servers') }}</a></li>
        <li><a href="{{ route('admin.servers.view', $server->id) }}">{{ $server->name }}</a></li>
        <li class="active">{{ __('Manage') }}</li>
    </ol>
@endsection

@section('content')
    @include('admin.servers.partials.navigation')
    <div class="row equal-height">
        <div class="col-sm-4">
            <div class="box box-danger">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ __('Reinstall Server') }}</h3>
                </div>
                <div class="box-body">
                    <p>{{ __('This will reinstall the server with the assigned service scripts.') }} <strong>{{ __('Danger!') }}</strong> {{ __('This could overwrite server data.') }}</p>
                </div>
                <div class="box-footer">
                    @if(! $server->canBeReinstalled())
                        <button class="btn btn-danger disabled">{{ __('Reinstall Server') }}</button>
                        <p style="padding-top: 1rem;">{{ __('This server is set to skip its install script. Disable "Skip Egg Install Script" on the startup page to reinstall it.') }}</p>
                    @elseif($server->isInstalled())
                        <form action="{{ route('admin.servers.view.manage.reinstall', $server->id) }}" method="POST">
                            {!! csrf_field() !!}
                            <button type="submit" class="btn btn-danger">{{ __('Reinstall Server') }}</button>
                        </form>
                    @else
                        <button class="btn btn-danger disabled">{{ __('Server Must Install Properly to Reinstall') }}</button>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ __('Install Status') }}</h3>
                </div>
                <div class="box-body">
                    <p>{{ __('If you need to change the install status from uninstalled to installed, or vice versa, you may do so with the button below.') }}</p>
                </div>
                <div class="box-footer">
                    <form action="{{ route('admin.servers.view.manage.toggle', $server->id) }}" method="POST">
                        {!! csrf_field() !!}
                        <button type="submit" class="btn btn-primary">{{ __('Toggle Install Status') }}</button>
                    </form>
                </div>
            </div>
        </div>

        @if(! $server->isSuspended())
            <div class="col-sm-4">
                <div class="box box-warning">
                    <div class="box-header with-border">
                        <h3 class="box-title">{{ __('Suspend Server') }}</h3>
                    </div>
                    <div class="box-body">
                        <p>{{ __('This will suspend the server, stop any running processes, and immediately block the user from being able to access their files or otherwise manage the server through the panel or API.') }}</p>
                    </div>
                    <div class="box-footer">
                        <form action="{{ route('admin.servers.view.manage.suspension', $server->id) }}" method="POST">
                            {!! csrf_field() !!}
                            <input type="hidden" name="action" value="suspend" />
                            <button type="submit" class="btn btn-warning @if(! is_null($server->transfer)) disabled @endif">{{ __('Suspend Server') }}</button>
                        </form>
                    </div>
                </div>
            </div>
        @else
            <div class="col-sm-4">
                <div class="box box-success">
                    <div class="box-header with-border">
                        <h3 class="box-title">{{ __('Unsuspend Server') }}</h3>
                    </div>
                    <div class="box-body">
                        <p>{{ __('This will unsuspend the server and restore normal user access.') }}</p>
                    </div>
                    <div class="box-footer">
                        <form action="{{ route('admin.servers.view.manage.suspension', $server->id) }}" method="POST">
                            {!! csrf_field() !!}
                            <input type="hidden" name="action" value="unsuspend" />
                            <button type="submit" class="btn btn-success">{{ __('Unsuspend Server') }}</button>
                        </form>
                    </div>
                </div>
            </div>
        @endif

        @if(is_null($server->transfer))
            <div class="col-sm-4">
                <div class="box box-success">
                    <div class="box-header with-border">
                        <h3 class="box-title">{{ __('Transfer Server') }}</h3>
                    </div>
                    <div class="box-body">
                        <p>
                            {{ __('Transfer this server to another node connected to this panel.') }}
                            <strong>{{ __('Warning!') }}</strong> {{ __('This feature has not been fully tested and may have bugs.') }}
                        </p>
                    </div>

                    <div class="box-footer">
                        @if($canTransfer)
                            <button class="btn btn-success" data-toggle="modal" data-target="#transferServerModal">{{ __('Transfer Server') }}</button>
                        @else
                            <button class="btn btn-success disabled">{{ __('Transfer Server') }}</button>
                            <p style="padding-top: 1rem;">{{ __('Transferring a server requires more than one node to be configured on your panel.') }}</p>
                        @endif
                    </div>
                </div>
            </div>
        @else
            <div class="col-sm-4">
                <div class="box box-success">
                    <div class="box-header with-border">
                        <h3 class="box-title">{{ __('Transfer Server') }}</h3>
                    </div>
                    <div class="box-body">
                        <p>
                            {{ __('This server is currently being transferred to another node.') }}
                            {{ __('Transfer was initiated at') }} <strong>{{ $server->transfer->created_at }}</strong>
                        </p>
                    </div>

                    <div class="box-footer">
                        <button class="btn btn-success disabled">{{ __('Transfer Server') }}</button>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <div class="modal fade" id="transferServerModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form action="{{ route('admin.servers.view.manage.transfer', $server->id) }}" method="POST">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-label="{{ __('Close') }}"><span aria-hidden="true">&times;</span></button>
                        <h4 class="modal-title">{{ __('Transfer Server') }}</h4>
                    </div>

                    <div class="modal-body">
                        <div class="row">
                            <div class="form-group col-md-12">
                                <label for="pNodeId">{{ __('Node') }}</label>
                                <select name="node_id" id="pNodeId" class="form-control">
                                    @foreach($locations as $location)
                                        <optgroup label="{{ $location->long }} ({{ $location->short }})">
                                            @foreach($location->nodes as $node)

                                                @if($node->id != $server->node_id)
                                                    <option value="{{ $node->id }}"
                                                            @if($location->id === old('location_id')) selected @endif
                                                    >{{ $node->name }}</option>
                                                @endif

                                            @endforeach
                                        </optgroup>
                                    @endforeach
                                </select>
                                <p class="small text-muted no-margin">{{ __('The node which this server will be transferred to.') }}</p>
                            </div>

                            <div class="form-group col-md-12">
                                <label for="pAllocation">{{ __('Default Allocation') }}</label>
                                <select name="allocation_id" id="pAllocation" class="form-control"></select>
                                <p class="small text-muted no-margin">{{ __('The main allocation that will be assigned to this server.') }}</p>
                            </div>

                            <div class="form-group col-md-12">
                                <label for="pAllocationAdditional">{{ __('Additional Allocation(s)') }}</label>
                                <select name="allocation_additional[]" id="pAllocationAdditional" class="form-control" multiple></select>
                                <p class="small text-muted no-margin">{{ __('Additional allocations to assign to this server on creation.') }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        {!! csrf_field() !!}
                        <button type="button" class="btn btn-default btn-sm pull-left" data-dismiss="modal">{{ __('Cancel') }}</button>
                        <button type="submit" class="btn btn-success btn-sm">{{ __('Confirm') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('footer-scripts')
    @parent
    {!! Theme::js('vendor/lodash/lodash.js') !!}

    @if($canTransfer)
        {!! Theme::js('js/admin/server/transfer.js') !!}
    @endif
@endsection
