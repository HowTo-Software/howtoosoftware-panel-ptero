@extends('layouts.admin')

@section('title')
    {{ __('Nodes &rarr; New') }}
@endsection

@section('content-header')
    <h1>{{ __('New Node') }}<small>{{ __('Create a new local or remote node for servers to be installed to.') }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">{{ __('Admin') }}</a></li>
        <li><a href="{{ route('admin.nodes') }}">{{ __('Nodes') }}</a></li>
        <li class="active">{{ __('New') }}</li>
    </ol>
@endsection

@section('content')
<form action="{{ route('admin.nodes.new') }}" method="POST">
    <div class="row">
        <div class="col-sm-6">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ __('Basic Details') }}</h3>
                </div>
                <div class="box-body">
                    <div class="form-group">
                        <label for="pName" class="form-label">{{ __('Name') }}</label>
                        <input type="text" name="name" id="pName" class="form-control" value="{{ old('name') }}"/>
                        <p class="text-muted small">{{ __('Character limits:') }} <code>a-zA-Z0-9_.-</code> {{ __('and') }} <code>[Space]</code> {{ __('(min 1, max 100 characters).') }}</p>
                    </div>
                    <div class="form-group">
                        <label for="pDescription" class="form-label">{{ __('Description') }}</label>
                        <textarea name="description" id="pDescription" rows="4" class="form-control">{{ old('description') }}</textarea>
                    </div>
                    <div class="form-group">
                        <label for="pLocationId" class="form-label">{{ __('Location') }}</label>
                        <select name="location_id" id="pLocationId">
                            @foreach($locations as $location)
                                <option value="{{ $location->id }}" {{ $location->id != old('location_id') ?: 'selected' }}>{{ $location->short }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">{{ __('Node Visibility') }}</label>
                        <div>
                            <div class="radio radio-success radio-inline">

                                <input type="radio" id="pPublicTrue" value="1" name="public" checked>
                                <label for="pPublicTrue"> {{ __('Public') }} </label>
                            </div>
                            <div class="radio radio-danger radio-inline">
                                <input type="radio" id="pPublicFalse" value="0" name="public">
                                <label for="pPublicFalse"> {{ __('Private') }} </label>
                            </div>
                        </div>
                        <p class="text-muted small">{{ __('By setting a node to') }} <code>private</code> {{ __('you will be denying the ability to auto-deploy to this node.') }}
                    </div>
                    <div class="form-group">
                        <label for="pFQDN" class="form-label">FQDN</label>
                        <input type="text" name="fqdn" id="pFQDN" class="form-control" value="{{ old('fqdn') }}"/>
                        <p class="text-muted small">{{ __('Please enter domain name (e.g') }} <code>node.example.com</code>{{ __(') to be used for connecting to the daemon. An IP address may be used') }} <em>{{ __('only') }}</em> {{ __('if you are not using SSL for this node.') }}</p>
                    </div>
                    <div class="form-group">
                        <label class="form-label">{{ __('Communicate Over SSL') }}</label>
                        <div>
                            <div class="radio radio-success radio-inline">
                                <input type="radio" id="pSSLTrue" value="https" name="scheme" {{ old('scheme', 'https') === 'https' ? 'checked' : '' }}>
                                <label for="pSSLTrue"> {{ __('Use SSL Connection') }}</label>
                            </div>
                            <div class="radio radio-danger radio-inline">
                                <input type="radio" id="pSSLFalse" value="http" name="scheme" {{ old('scheme') === 'http' ? 'checked' : '' }}>
                                <label for="pSSLFalse"> {{ __('Use HTTP Connection') }}</label>
                            </div>
                        </div>
                        @if(request()->isSecure())
                            <p class="text-warning small">{{ __('HTTP can be selected, but browsers block insecure connections from an HTTPS panel. Use SSL if you need the browser console and other direct browser connections to this node.') }}</p>
                        @else
                            <p class="text-muted small">{{ __('In most cases you should select to use a SSL connection. If using an IP Address or you do not wish to use SSL at all, select a HTTP connection.') }}</p>
                        @endif
                    </div>
                    <div class="form-group">
                        <label class="form-label">{{ __('Behind Proxy') }}</label>
                        <div>
                            <div class="radio radio-success radio-inline">
                                <input type="radio" id="pProxyFalse" value="0" name="behind_proxy" checked>
                                <label for="pProxyFalse"> {{ __('Not Behind Proxy') }} </label>
                            </div>
                            <div class="radio radio-info radio-inline">
                                <input type="radio" id="pProxyTrue" value="1" name="behind_proxy">
                                <label for="pProxyTrue"> {{ __('Behind Proxy') }} </label>
                            </div>
                        </div>
                        <p class="text-muted small">{{ __('If you are running the daemon behind a proxy such as Cloudflare, select this to have the daemon skip looking for certificates on boot.') }}</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ __('Configuration') }}</h3>
                </div>
                <div class="box-body">
                    <div class="row">
                        <div class="form-group col-md-6">
                            <label for="pDaemonBase" class="form-label">{{ __('Daemon Server File Directory') }}</label>
                            <input type="text" name="daemonBase" id="pDaemonBase" class="form-control" value="/var/lib/pterodactyl/volumes" />
                            <p class="text-muted small">{{ __('Enter the directory where server files should be stored.') }} <strong>{{ __('If you use OVH you should check your partition scheme. You may need to use') }} <code>/home/daemon-data</code> {{ __('to have enough space.') }}</strong></p>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="pMemory" class="form-label">{{ __('Total Memory') }}</label>
                            <div class="input-group">
                                <input type="text" name="memory" data-multiplicator="true" class="form-control" id="pMemory" value="{{ old('memory') }}"/>
                                <span class="input-group-addon">MiB</span>
                            </div>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="pMemoryOverallocate" class="form-label">{{ __('Memory Over-Allocation') }}</label>
                            <div class="input-group">
                                <input type="text" name="memory_overallocate" class="form-control" id="pMemoryOverallocate" value="{{ old('memory_overallocate') }}"/>
                                <span class="input-group-addon">%</span>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <p class="text-muted small">{{ __('Enter the total amount of memory available for new servers. If you would like to allow overallocation of memory enter the percentage that you want to allow. To disable checking for overallocation enter') }} <code>-1</code> {{ __('into the field. Entering') }} <code>0</code> {{ __('will prevent creating new servers if it would put the node over the limit.') }}</p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="form-group col-md-6">
                            <label for="pDisk" class="form-label">{{ __('Total Disk Space') }}</label>
                            <div class="input-group">
                                <input type="text" name="disk" data-multiplicator="true" class="form-control" id="pDisk" value="{{ old('disk') }}"/>
                                <span class="input-group-addon">MiB</span>
                            </div>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="pDiskOverallocate" class="form-label">{{ __('Disk Over-Allocation') }}</label>
                            <div class="input-group">
                                <input type="text" name="disk_overallocate" class="form-control" id="pDiskOverallocate" value="{{ old('disk_overallocate') }}"/>
                                <span class="input-group-addon">%</span>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <p class="text-muted small">{{ __('Enter the total amount of disk space available for new servers. If you would like to allow overallocation of disk space enter the percentage that you want to allow. To disable checking for overallocation enter') }} <code>-1</code> {{ __('into the field. Entering') }} <code>0</code> {{ __('will prevent creating new servers if it would put the node over the limit.') }}</p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="form-group col-md-6">
                            <label for="pDaemonListen" class="form-label">{{ __('Daemon Port') }}</label>
                            <input type="text" name="daemonListen" class="form-control" id="pDaemonListen" value="8080" />
                        </div>
                        <div class="form-group col-md-6">
                            <label for="pDaemonSFTP" class="form-label">{{ __('Daemon SFTP Port') }}</label>
                            <input type="text" name="daemonSFTP" class="form-control" id="pDaemonSFTP" value="2022" />
                        </div>
                        <div class="col-md-12">
                            <p class="text-muted small">{{ __('The daemon runs its own SFTP management container and does not use the SSHd process on the main physical server.') }} <strong>{{ __('Do not use the same port that you have assigned for your physical server\'s SSH process.') }}</strong> {{ __('If you will be running the daemon behind CloudFlare® you should set the daemon port to') }} <code>8443</code> {{ __('to allow websocket proxying over SSL.') }}</p>
                        </div>
                    </div>
                </div>
                <div class="box-footer">
                    {!! csrf_field() !!}
                    <button type="submit" class="btn btn-success pull-right">{{ __('Create Node') }}</button>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection

@section('footer-scripts')
    @parent
    <script>
        $('#pLocationId').select2();
    </script>
@endsection
