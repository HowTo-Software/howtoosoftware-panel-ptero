@extends('layouts.admin')

@section('title')
    {{ __('Integrations') }}
@endsection

@section('content-header')
    <h1>{{ __('External integrations') }}<small>{{ __('Configure server-side providers without exposing credentials to the browser.') }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">{{ __('Integrations') }}</li>
    </ol>
@endsection

@section('content')
    <form action="{{ route('admin.settings.integrations') }}" method="POST" autocomplete="off">
        {!! csrf_field() !!}
        <input type="hidden" name="_method" value="PATCH">

        <div class="row">
            @foreach ($providers as $provider => $status)
                <div class="col-md-6">
                    <div class="box">
                        <div class="box-header with-border">
                            <h3 class="box-title">{{ strtoupper($provider) }}</h3>
                            <span class="label {{ $status['configured'] ? 'label-success' : 'label-default' }} pull-right">
                                {{ $status['configured'] ? __('Configured') : __('Not configured') }}
                            </span>
                        </div>
                        <div class="box-body">
                            <div class="form-group">
                                <label>
                                    <input type="hidden" name="providers[{{ $provider }}][enabled]" value="0">
                                    <input type="checkbox" name="providers[{{ $provider }}][enabled]" value="1" @checked(old("providers.$provider.enabled", $status['enabled']))>
                                    {{ __('Enabled') }}
                                </label>
                            </div>

                            <div class="form-group">
                                <label for="{{ $provider }}-secret">API key</label>
                                <input id="{{ $provider }}-secret" class="form-control" type="password"
                                       name="providers[{{ $provider }}][secret]" value=""
                                       placeholder="{{ $status['configured'] ? __('Keep current secret') : '' }}"
                                       autocomplete="new-password" maxlength="512">
                            </div>

                            @if (in_array($provider, ['gemini', 'groq'], true))
                                <div class="form-group">
                                    <label for="{{ $provider }}-model">{{ __('Model') }}</label>
                                    <input id="{{ $provider }}-model" class="form-control" type="text"
                                           name="providers[{{ $provider }}][model]"
                                           value="{{ old("providers.$provider.model", $status['model']) }}"
                                           maxlength="120" autocomplete="off">
                                </div>
                            @else
                                <input type="hidden" name="providers[{{ $provider }}][model]" value="">
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="box">
            <div class="box-body">
                <p class="text-muted">{{ __('Keys are encrypted with APP_KEY and are never returned to this page.') }}</p>
                <button type="submit" class="btn btn-primary pull-right">{{ __('Save integrations') }}</button>
            </div>
        </div>
    </form>
@endsection
