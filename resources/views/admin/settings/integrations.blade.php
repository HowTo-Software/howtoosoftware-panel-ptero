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
                            <div class="row">
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label>
                                            <input type="hidden" name="providers[{{ $provider }}][enabled]" value="0">
                                            <input type="checkbox" name="providers[{{ $provider }}][enabled]" value="1" @checked(old("providers.$provider.enabled", $status['enabled']))>
                                            {{ __('Enabled') }}
                                        </label>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label for="{{ $provider }}-priority">{{ __('Provider priority') }}</label>
                                        <input id="{{ $provider }}-priority" class="form-control" type="number" min="1" max="1000"
                                               name="providers[{{ $provider }}][priority]"
                                               value="{{ old("providers.$provider.priority", $status['priority']) }}" required>
                                    </div>
                                </div>
                            </div>

                            @if (in_array($provider, ['gemini', 'groq'], true))
                                <div class="form-group">
                                    <label for="{{ $provider }}-model">{{ __('Model') }}</label>
                                    <input id="{{ $provider }}-model" class="form-control" type="text"
                                           name="providers[{{ $provider }}][model]"
                                           value="{{ old("providers.$provider.model", $status['model']) }}"
                                           maxlength="120" autocomplete="off">
                                    <p class="text-muted small">{{ __('Lower provider priority values are attempted first.') }}</p>
                                </div>
                            @else
                                <input type="hidden" name="providers[{{ $provider }}][model]" value="">
                            @endif

                            <div class="form-group">
                                <label>
                                    <input type="hidden" name="providers[{{ $provider }}][environment_key_enabled]" value="0">
                                    <input type="checkbox" name="providers[{{ $provider }}][environment_key_enabled]" value="1"
                                           @checked(old("providers.$provider.environment_key_enabled", $status['environment_key_enabled']))
                                           @disabled(!$status['environment_configured'])>
                                    {{ __('Use key configured in the environment') }}
                                </label>
                                <span class="label {{ $status['environment_configured'] ? 'label-info' : 'label-default' }} pull-right">
                                    {{ $status['environment_configured'] ? __('Available') : __('Not set') }}
                                </span>
                            </div>

                            <hr>
                            <div class="clearfix" style="margin-bottom: 10px;">
                                <strong>{{ __('API keys') }}</strong>
                                <button type="button" class="btn btn-xs btn-success pull-right add-integration-key" data-provider="{{ $provider }}">
                                    <i class="fa fa-plus"></i> {{ __('Add API key') }}
                                </button>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-condensed integration-keys" data-provider="{{ $provider }}">
                                    <thead>
                                        <tr>
                                            <th>{{ __('Name') }}</th>
                                            <th style="width: 85px;">{{ __('Priority') }}</th>
                                            <th style="width: 70px;">{{ __('Enabled') }}</th>
                                            <th>{{ __('Replace secret') }}</th>
                                            <th style="width: 65px;">{{ __('Delete') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($status['keys'] as $key)
                                            <tr>
                                                <td>
                                                    <input type="hidden" name="providers[{{ $provider }}][keys][existing-{{ $key['id'] }}][id]" value="{{ $key['id'] }}">
                                                    <input class="form-control input-sm" type="text" maxlength="80" required
                                                           name="providers[{{ $provider }}][keys][existing-{{ $key['id'] }}][name]"
                                                           value="{{ $key['name'] }}">
                                                    @if ($key['cooling_down'])
                                                        <span class="label label-warning">{{ __('Cooldown') }}: {{ $key['last_failure_reason'] }}</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    <input class="form-control input-sm" type="number" min="1" max="1000" required
                                                           name="providers[{{ $provider }}][keys][existing-{{ $key['id'] }}][priority]"
                                                           value="{{ $key['priority'] }}">
                                                </td>
                                                <td class="text-center">
                                                    <input type="hidden" name="providers[{{ $provider }}][keys][existing-{{ $key['id'] }}][enabled]" value="0">
                                                    <input type="checkbox" name="providers[{{ $provider }}][keys][existing-{{ $key['id'] }}][enabled]" value="1" @checked($key['enabled'])>
                                                </td>
                                                <td>
                                                    <input class="form-control input-sm" type="password" maxlength="512" autocomplete="new-password"
                                                           name="providers[{{ $provider }}][keys][existing-{{ $key['id'] }}][secret]" value=""
                                                           placeholder="{{ __('Keep current secret') }}">
                                                </td>
                                                <td class="text-center">
                                                    <input type="hidden" name="providers[{{ $provider }}][keys][existing-{{ $key['id'] }}][delete]" value="0">
                                                    <input type="checkbox" name="providers[{{ $provider }}][keys][existing-{{ $key['id'] }}][delete]" value="1">
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <template id="{{ $provider }}-key-template">
                                <tr>
                                    <td>
                                        <input class="form-control input-sm" type="text" maxlength="80" required
                                               name="providers[{{ $provider }}][keys][__INDEX__][name]" value="API Key">
                                    </td>
                                    <td>
                                        <input class="form-control input-sm" type="number" min="1" max="1000" required
                                               name="providers[{{ $provider }}][keys][__INDEX__][priority]" value="100">
                                    </td>
                                    <td class="text-center">
                                        <input type="hidden" name="providers[{{ $provider }}][keys][__INDEX__][enabled]" value="0">
                                        <input type="checkbox" name="providers[{{ $provider }}][keys][__INDEX__][enabled]" value="1" checked>
                                    </td>
                                    <td>
                                        <input class="form-control input-sm" type="password" maxlength="512" required autocomplete="new-password"
                                               name="providers[{{ $provider }}][keys][__INDEX__][secret]" value="">
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-xs btn-danger remove-new-integration-key" title="{{ __('Remove') }}">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            </template>
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

@section('footer-scripts')
    @parent
    <script>
        document.querySelectorAll('.add-integration-key').forEach(function (button) {
            button.addEventListener('click', function () {
                var provider = button.getAttribute('data-provider');
                var template = document.getElementById(provider + '-key-template');
                var table = document.querySelector('.integration-keys[data-provider="' + provider + '"] tbody');
                var index = 'new-' + Date.now() + '-' + Math.floor(Math.random() * 100000);
                table.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', index));
            });
        });

        document.addEventListener('click', function (event) {
            var button = event.target.closest('.remove-new-integration-key');
            if (button) {
                button.closest('tr').remove();
            }
        });
    </script>
@endsection
