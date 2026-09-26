@extends('layouts.admin')

@section('title')
    {{ __('Manage User:') }} {{ $user->username }}
@endsection

@section('content-header')
    <h1>{{ $user->name_first }} {{ $user->name_last}}<small>{{ $user->username }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">{{ __('Admin') }}</a></li>
        <li><a href="{{ route('admin.users') }}">{{ __('Users') }}</a></li>
        <li class="active">{{ $user->username }}</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <form action="{{ route('admin.users.view', $user->id) }}" method="post">
        <div class="col-md-6">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ __('Identity') }}</h3>
                </div>
                <div class="box-body">
                    <div class="form-group">
                        <label for="email" class="control-label">{{ __('Email') }}</label>
                        <div>
                            <input type="email" name="email" value="{{ $user->email }}" class="form-control form-autocomplete-stop">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="registered" class="control-label">{{ __('Username') }}</label>
                        <div>
                            <input type="text" name="username" value="{{ $user->username }}" class="form-control form-autocomplete-stop">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="registered" class="control-label">{{ __('Client First Name') }}</label>
                        <div>
                            <input type="text" name="name_first" value="{{ $user->name_first }}" class="form-control form-autocomplete-stop">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="registered" class="control-label">{{ __('Client Last Name') }}</label>
                        <div>
                            <input type="text" name="name_last" value="{{ $user->name_last }}" class="form-control form-autocomplete-stop">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="control-label">{{ __('Default Language') }}</label>
                        <div>
                            <select name="language" class="form-control">
                                @foreach($languages as $key => $value)
                                    <option value="{{ $key }}" @if($user->language === $key) selected @endif>{{ $value }}</option>
                                @endforeach
                            </select>
                            <p class="text-muted"><small>{{ __('The default language to use when rendering the Panel for this user.') }}</small></p>
                        </div>
                    </div>
                </div>
                <div class="box-footer">
                    {!! csrf_field() !!}
                    {!! method_field('PATCH') !!}
                    <input type="submit" value="Update User" class="btn btn-primary btn-sm">
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ __('Password') }}</h3>
                </div>
                <div class="box-body">
                    @if($ssoUserUrl !== '')
                        <p>{{ __('Passwords live in Active Directory, so they are reset in Authentik rather than here.') }}</p>
                        <div class="alert alert-warning no-margin">
                            {{ __('Authentik may only write into') }} <code>OU=PanelUsers</code>{{ __(', so this works for customer accounts. A staff reset fails the write back to Active Directory with') }} <code>Failed to set password</code> {{ __('and has to be done on the domain controller.') }}
                        </div>
                    @else
                        <div class="alert alert-success" style="display:none;margin-bottom:10px;" id="gen_pass"></div>
                        <div class="form-group no-margin-bottom">
                            <label for="password" class="control-label">{{ __('Password') }} <span class="field-optional"></span></label>
                            <div>
                                <input type="password" id="password" name="password" class="form-control form-autocomplete-stop">
                                <p class="text-muted small">{{ __('Leave blank to keep this user\'s password the same. User will not receive any notification if password is changed.') }}</p>
                            </div>
                        </div>
                    @endif
                </div>
                @if($ssoUserUrl !== '')
                    <div class="box-footer">
                        <a href="{{ $ssoUserUrl }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-primary">{{ __('Reset Password') }}</a>
                    </div>
                @endif
            </div>
        </div>
        <div class="col-md-6">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ __('Permissions') }}</h3>
                </div>
                <div class="box-body">
                    <div class="form-group">
                        <label for="root_admin" class="control-label">{{ __('Administrator') }}</label>
                        <div>
                            <select name="root_admin" class="form-control">
                                <option value="0">@lang('strings.no')</option>
                                <option value="1" {{ $user->root_admin ? 'selected="selected"' : '' }}>@lang('strings.yes')</option>
                            </select>
                            <p class="text-muted"><small>{{ __('Setting this to \'Yes\' gives a user full administrative access.') }}</small></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
    <div class="col-xs-12">
        <div class="box box-danger">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('Delete User') }}</h3>
            </div>
            <div class="box-body">
                <p class="no-margin">{{ __('There must be no servers associated with this account in order for it to be deleted.') }}</p>
            </div>
            <div class="box-footer">
                <form action="{{ route('admin.users.view', $user->id) }}" method="POST">
                    {!! csrf_field() !!}
                    {!! method_field('DELETE') !!}
                    <input id="delete" type="submit" class="btn btn-sm btn-danger pull-right" {{ $user->servers->count() < 1 ?: 'disabled' }} value="Delete User" />
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
