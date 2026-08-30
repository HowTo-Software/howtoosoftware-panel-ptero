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
                                <div class="{{ $provider === 'ollama' ? 'col-sm-4' : 'col-sm-6' }}">
                                    <div class="form-group">
                                        <label>
                                            <input type="hidden" name="providers[{{ $provider }}][enabled]" value="0">
                                            <input type="checkbox" name="providers[{{ $provider }}][enabled]" value="1" @checked(old("providers.$provider.enabled", $status['enabled']))>
                                            {{ __('Enabled') }}
                                        </label>
                                    </div>
                                </div>
                                <div class="{{ $provider === 'ollama' ? 'col-sm-4' : 'col-sm-6' }}">
                                    <div class="form-group">
                                        <label for="{{ $provider }}-priority">{{ __('Provider priority') }}</label>
                                        <input id="{{ $provider }}-priority" class="form-control" type="number" min="1" max="1000"
                                               name="providers[{{ $provider }}][priority]"
                                               value="{{ old("providers.$provider.priority", $status['priority']) }}" required>
                                    </div>
                                </div>
                                @if ($provider === 'ollama')
                                    <div class="col-sm-4">
                                        <div class="form-group">
                                            <label for="{{ $provider }}-timeout">{{ __('Attempt timeout') }}</label>
                                            <div class="input-group">
                                                <input id="{{ $provider }}-timeout" class="form-control" type="number" min="5" max="180"
                                                       name="providers[{{ $provider }}][timeout_seconds]"
                                                       value="{{ old("providers.$provider.timeout_seconds", $status['timeout_seconds']) }}" required>
                                                <span class="input-group-addon">{{ __('seconds') }}</span>
                                            </div>
                                        </div>
                                    </div>
                                @else
                                    <input type="hidden" name="providers[{{ $provider }}][timeout_seconds]" value="{{ $status['timeout_seconds'] }}">
                                @endif
                            </div>

                            @if ($provider === 'ollama')
                                <div class="form-group">
                                    <label for="ollama-base-url">{{ __('Base URL') }}</label>
                                    <input id="ollama-base-url" class="form-control" type="url"
                                           name="providers[ollama][base_url]"
                                           value="{{ old('providers.ollama.base_url', $status['base_url']) }}"
                                           placeholder="http://192.168.x.x:11435" maxlength="255" autocomplete="off">
                                    <p class="text-muted small">{{ __('Stored server-side. Do not include /api/chat or /api/tags.') }}</p>
                                </div>
                                <div class="form-group">
                                    <label for="{{ $provider }}-model">{{ __('Model') }}</label>
                                    @php($knownModels = collect($ollamaDiscovery['models'] ?? [])->push($status['model'])->filter()->unique()->values())
                                    <select id="{{ $provider }}-model" class="form-control" name="providers[{{ $provider }}][model]">
                                        <option value="">{{ __('Select a discovered model') }}</option>
                                        @foreach ($knownModels as $model)
                                            <option value="{{ $model }}" @selected(old("providers.$provider.model", $status['model']) === $model)>{{ $model }}</option>
                                        @endforeach
                                    </select>
                                    <p class="text-muted small">
                                        {{ __('Connection status:') }}
                                        @if (($ollamaDiscovery['connected'] ?? null) === true)
                                            <span class="label label-success">{{ __('Connected') }}</span>
                                        @elseif (($ollamaDiscovery['connected'] ?? null) === false)
                                            <span class="label label-danger">{{ __('Unavailable') }}</span>
                                        @else
                                            <span class="label label-default">{{ __('Not tested') }}</span>
                                        @endif
                                        <button type="submit" form="ollama-model-refresh" class="btn btn-xs btn-default pull-right">
                                            <i class="fa fa-refresh"></i> {{ __('Test connection / Refresh models') }}
                                        </button>
                                    </p>
                                </div>
                            @else
                                <input type="hidden" name="providers[{{ $provider }}][model]" value="">
                                <input type="hidden" name="providers[{{ $provider }}][base_url]" value="">
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

                            @if ($provider === 'ollama')
                                <div class="form-group">
                                    <label for="ollama-secret">{{ __('API Key') }}</label>
                                    <input id="ollama-secret" class="form-control" type="password" maxlength="512"
                                           name="providers[ollama][secret]" value="" autocomplete="new-password"
                                           placeholder="{{ $status['configured'] ? __('Keep current API key') : __('Bearer API key') }}">
                                    <p class="text-muted small">{{ __('Encrypted with APP_KEY and never returned to this page or the browser client.') }}</p>
                                </div>
                            @else
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
    <form id="ollama-model-refresh" action="{{ route('admin.settings.integrations.ollama-models') }}" method="POST">
        {!! csrf_field() !!}
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
