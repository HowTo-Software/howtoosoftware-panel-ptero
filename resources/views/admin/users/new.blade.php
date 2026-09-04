@extends('layouts.admin')

@section('title')
    Create User
@endsection

@section('content-header')
    <h1>Create User<small>Add a new user to the system.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.users') }}">Users</a></li>
        <li class="active">Create</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <form method="post">
        <div class="col-md-6">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Identity</h3>
                </div>
                <div class="box-body">
                    <div class="form-group">
                        <label for="email" class="control-label">Email</label>
                        <div>
                            <input type="text" autocomplete="off" name="email" value="{{ old('email') }}" class="form-control" />
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="username" class="control-label">Username</label>
                        <div>
                            <input type="text" autocomplete="off" name="username" value="{{ old('username') }}" class="form-control" />
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="name_first" class="control-label">Client First Name</label>
                        <div>
                            <input type="text" autocomplete="off" name="name_first" value="{{ old('name_first') }}" class="form-control" />
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="name_last" class="control-label">Client Last Name</label>
                        <div>
                            <input type="text" autocomplete="off" name="name_last" value="{{ old('name_last') }}" class="form-control" />
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="control-label">Default Language</label>
                        <div>
                            <select name="language" class="form-control">
                                @foreach($languages as $key => $value)
                                    <option value="{{ $key }}" @if(config('app.locale') === $key) selected @endif>{{ $value }}</option>
                                @endforeach
                            </select>
                            <p class="text-muted"><small>The default language to use when rendering the Panel for this user.</small></p>
                        </div>
                    </div>
                </div>
                <div class="box-footer">
                    {!! csrf_field() !!}
                    <input type="submit" value="Create User" class="btn btn-success btn-sm">
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">Permissions</h3>
                </div>
                <div class="box-body">
                    <div class="form-group col-md-12">
                        <label for="root_admin" class="control-label">Administrator</label>
                        <div>
                            <select name="root_admin" class="form-control">
                                <option value="0">@lang('strings.no')</option>
                                <option value="1">@lang('strings.yes')</option>
                            </select>
                            <p class="text-muted"><small>Setting this to 'Yes' gives a user full administrative access.</small></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">Password</h3>
                </div>
                <div class="box-body">
                    <div class="alert alert-warning">
                        <p>Leave this blank. Passwords live in Active Directory and users sign in with Single Sign-On, so a password set here is <strong>not</strong> the one they log in with.</p>
                        <p class="no-margin">It is a break-glass credential only: the sign-in screen has no password field, and it is accepted solely by a direct <code>POST /auth/login</code> if Authentik or Active Directory is unavailable. If you need one, generate it below and record it now &mdash; it is never shown again.</p>
                    </div>
                    <div id="gen_pass" class="alert alert-success" style="display:none;margin-bottom:10px;"></div>
                    <div class="form-group">
                        <label for="pass" class="control-label">Password <span class="field-optional"></span></label>
                        <div class="input-group">
                            <input type="password" id="pass" name="password" class="form-control form-autocomplete-stop" autocomplete="new-password" />
                            <span class="input-group-btn">
                                <button type="button" id="gen_pass_bttn" class="btn btn-default">Generate</button>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@section('footer-scripts')
    @parent
    <script>
        $('#gen_pass_bttn').on('click', function (event) {
            event.preventDefault();

            // Generated in the browser, so the value crosses the network once on
            // submit. The endpoint the previous version fetched was never routed.
            var alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789-_=+!@#%^*';
            // Reject the tail of the byte range, or the first characters of the
            // alphabet would come up more often than the last.
            var limit = Math.floor(256 / alphabet.length) * alphabet.length;
            var byte = new Uint8Array(1);
            var password = '';

            while (password.length < 24) {
                window.crypto.getRandomValues(byte);
                if (byte[0] < limit) {
                    password += alphabet.charAt(byte[0] % alphabet.length);
                }
            }

            $('#pass').val(password);
            // .text() not .html(): the alphabet contains characters that would
            // otherwise be parsed as markup.
            $('#gen_pass').text('Generated password: ' + password).slideDown();
        });
    </script>
@endsection
