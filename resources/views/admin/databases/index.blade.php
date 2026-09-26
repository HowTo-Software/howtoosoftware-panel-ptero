@extends('layouts.admin')

@section('title')
    {{ __('Database Hosts') }}
@endsection

@section('content-header')
    <h1>{{ __('Database Hosts') }}<small>{{ __('Database hosts that servers can have databases created on.') }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">{{ __('Admin') }}</a></li>
        <li class="active">{{ __('Database Hosts') }}</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('Host List') }}</h3>
                <div class="box-tools">
                    <button class="btn btn-sm btn-primary" data-toggle="modal" data-target="#newHostModal">{{ __('Create New') }}</button>
                </div>
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <tbody>
                        <tr>
                            <th>ID</th>
                            <th>{{ __('Name') }}</th>
                            <th>{{ __('Host') }}</th>
                            <th>{{ __('Port') }}</th>
                            <th>{{ __('Username') }}</th>
                            <th class="text-center">{{ __('Databases') }}</th>
                            <th class="text-center">{{ __('Node') }}</th>
                        </tr>
                        @foreach ($hosts as $host)
                            <tr>
                                <td><code>{{ $host->id }}</code></td>
                                <td><a href="{{ route('admin.databases.view', $host->id) }}">{{ $host->name }}</a></td>
                                <td><code>{{ $host->host }}</code></td>
                                <td><code>{{ $host->port }}</code></td>
                                <td>{{ $host->username }}</td>
                                <td class="text-center">{{ $host->databases_count }}</td>
                                <td class="text-center">
                                    @if(! is_null($host->node))
                                        <a href="{{ route('admin.nodes.view', $host->node->id) }}">{{ $host->node->name }}</a>
                                    @else
                            <span class="label label-default">{{ __('None') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="newHostModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form action="{{ route('admin.databases') }}" method="POST">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="{{ __('Close') }}"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title">{{ __('Create New Database Host') }}</h4>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="pName" class="form-label">{{ __('Name') }}</label>
                        <input type="text" name="name" id="pName" class="form-control" />
                        <p class="text-muted small">{{ __('A short identifier used to distinguish this location from others. Must be between 1 and 60 characters, for example,') }} <code>us.nyc.lvl3</code>.</p>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <label for="pHost" class="form-label">{{ __('Host') }}</label>
                            <input type="text" name="host" id="pHost" class="form-control" />
                            <p class="text-muted small">{{ __('The IP address or FQDN that should be used when attempting to connect to this MySQL host') }} <em>{{ __('from the panel') }}</em> {{ __('to add new databases.') }}</p>
                        </div>
                        <div class="col-md-6">
                            <label for="pPort" class="form-label">{{ __('Port') }}</label>
                            <input type="text" name="port" id="pPort" class="form-control" value="3306"/>
                            <p class="text-muted small">{{ __('The port that MySQL is running on for this host.') }}</p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <label for="pUsername" class="form-label">{{ __('Username') }}</label>
                            <input type="text" name="username" id="pUsername" class="form-control" />
                            <p class="text-muted small">{{ __('The username of an account that has enough permissions to create new users and databases on the system.') }}</p>
                        </div>
                        <div class="col-md-6">
                            <label for="pPassword" class="form-label">{{ __('Password') }}</label>
                            <input type="password" name="password" id="pPassword" class="form-control" />
                            <p class="text-muted small">{{ __('The password to the account defined.') }}</p>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="pNodeId" class="form-label">{{ __('Linked Node') }}</label>
                        <select name="node_id" id="pNodeId" class="form-control">
                                <option value="">{{ __('None') }}</option>
                            @foreach($locations as $location)
                                <optgroup label="{{ $location->short }}">
                                    @foreach($location->nodes as $node)
                                        <option value="{{ $node->id }}">{{ $node->name }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                        <p class="text-muted small">{{ __('This setting does nothing other than default to this database host when adding a database to a server on the selected node.') }}</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <p class="text-danger small text-left">{{ __('The account defined for this database host') }} <strong>{{ __('must') }}</strong> {{ __('have the') }} <code>WITH GRANT OPTION</code> {{ __('permission. If the defined account does not have this permission requests to create databases') }} <em>{{ __('will') }}</em> {{ __('fail.') }} <strong>{{ __('Do not use the same account details for MySQL that you have defined for this panel.') }}</strong></p>
                    {!! csrf_field() !!}
                    <button type="button" class="btn btn-default btn-sm pull-left" data-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-success btn-sm">{{ __('Create') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('footer-scripts')
    @parent
    <script>
        $('#pNodeId').select2();
    </script>
@endsection
