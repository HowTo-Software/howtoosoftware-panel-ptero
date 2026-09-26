@extends('layouts.admin')

@section('title')
    {{ __('Locations') }}
@endsection

@section('content-header')
    <h1>{{ __('Locations') }}<small>{{ __('All locations that nodes can be assigned to for easier categorization.') }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">{{ __('Admin') }}</a></li>
        <li class="active">{{ __('Locations') }}</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('Location List') }}</h3>
                <div class="box-tools">
                    <button class="btn btn-sm btn-primary" data-toggle="modal" data-target="#newLocationModal">{{ __('Create New') }}</button>
                </div>
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <tbody>
                        <tr>
                            <th>ID</th>
                            <th>{{ __('Short Code') }}</th>
                            <th>{{ __('Description') }}</th>
                            <th class="text-center">{{ __('Nodes') }}</th>
                            <th class="text-center">{{ __('Servers') }}</th>
                        </tr>
                        @foreach ($locations as $location)
                            <tr>
                                <td><code>{{ $location->id }}</code></td>
                                <td><a href="{{ route('admin.locations.view', $location->id) }}">{{ $location->short }}</a></td>
                                <td>{{ $location->long }}</td>
                                <td class="text-center">{{ $location->nodes_count }}</td>
                                <td class="text-center">{{ $location->servers_count }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="newLocationModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form action="{{ route('admin.locations') }}" method="POST">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="{{ __('Close') }}"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title">{{ __('Create Location') }}</h4>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-12">
                            <label for="pShortModal" class="form-label">{{ __('Short Code') }}</label>
                            <input type="text" name="short" id="pShortModal" class="form-control" />
                            <p class="text-muted small">{{ __('A short identifier used to distinguish this location from others. Must be between 1 and 60 characters, for example,') }} <code>us.nyc.lvl3</code>.</p>
                        </div>
                        <div class="col-md-12">
                            <label for="pLongModal" class="form-label">{{ __('Description') }}</label>
                            <textarea name="long" id="pLongModal" class="form-control" rows="4"></textarea>
                            <p class="text-muted small">{{ __('A longer description of this location. Must be less than 191 characters.') }}</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    {!! csrf_field() !!}
                    <button type="button" class="btn btn-default btn-sm pull-left" data-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-success btn-sm">{{ __('Create') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
