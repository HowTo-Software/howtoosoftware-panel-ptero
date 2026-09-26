@extends('layouts.admin')

@section('title')
    {{ __('Application API') }}
@endsection

@section('content-header')
    <h1>{{ __('Application API') }}<small>{{ __('Control access credentials for managing this Panel via the API.') }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">{{ __('Admin') }}</a></li>
        <li class="active">{{ __('Application API') }}</li>
    </ol>
@endsection

@section('content')
    <div class="row">
        <div class="col-xs-12">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ __('Credentials List') }}</h3>
                    <div class="box-tools">
                        <a href="{{ route('admin.api.new') }}" class="btn btn-sm btn-primary">{{ __('Create New') }}</a>
                    </div>
                </div>
                <div class="box-body table-responsive no-padding">
                    <table class="table table-hover">
                        <tr>
                            <th>{{ __('Key') }}</th>
                            <th>{{ __('Memo') }}</th>
                            <th>{{ __('Last Used') }}</th>
                            <th>{{ __('Created') }}</th>
                            <th>{{ __('Created by') }}</th>
                            <th></th>
                        </tr>
                        @foreach($keys as $key)
                            <tr>
                                <td><code>
                                    @if (Auth::user()->is($key->user))
                                        {{ $key->identifier . decrypt($key->token) }}
                                    @else
                                        {{ $key->identifier . '****' }}
                                    @endif
                                </code></td>
                                <td>{{ $key->memo }}</td>
                                <td>
                                    @if(!is_null($key->last_used_at))
                                        @datetimeHuman($key->last_used_at)
                                    @else
                                        &mdash;
                                    @endif
                                </td>
                                <td>@datetimeHuman($key->created_at)</td>
                                <td>
                                    <a href="{{ route('admin.users.view', $key->user->id) }}">{{ $key->user->username }}</a>
                                </td>
                                <td>
                                    <a href="#" data-action="revoke-key" data-attr="{{ $key->identifier }}">
                                        <i class="fa fa-trash-o text-danger"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('footer-scripts')
    @parent
    <script>
        $(document).ready(function() {
            $('[data-action="revoke-key"]').click(function (event) {
                var self = $(this);
                event.preventDefault();
                swal({
                    type: 'error',
                    title: @json(__('Revoke API Key')),
                    text: @json(__('Once this API key is revoked any applications currently using it will stop working.')),
                    showCancelButton: true,
                    allowOutsideClick: true,
                    closeOnConfirm: false,
                    confirmButtonText: 'Revoke',
                    confirmButtonColor: '#d9534f',
                    showLoaderOnConfirm: true
                }, function () {
                    $.ajax({
                        method: 'DELETE',
                        url: '/admin/api/revoke/' + self.data('attr'),
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    }).done(function () {
                        swal({
                            type: 'success',
                            title: '',
                        text: @json(__('API Key has been revoked.'))
                        });
                        self.parent().parent().slideUp();
                    }).fail(function (jqXHR) {
                        console.error(jqXHR);
                        swal({
                            type: 'error',
                        title: @json(__('Whoops!')),
                        text: @json(__('An error occurred while attempting to revoke this key.'))
                        });
                    });
                });
            });
        });
    </script>
@endsection
