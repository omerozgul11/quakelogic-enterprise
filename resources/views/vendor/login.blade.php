@extends('vendor.layout')
@section('title', 'Sign in')

@section('content')
    <div class="login">
        <div class="card">
            <h2>Vendor sign in</h2>
            <p class="muted" style="margin-top:-4px;">Access your purchase orders, quotations, and bills.</p>
            <form method="POST" action="{{ route('vendor.login.attempt') }}">
                @csrf
                <div class="field">
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" autofocus required>
                    @error('email')<div class="err-text">{{ $message }}</div>@enderror
                </div>
                <div class="field">
                    <label for="password">Password</label>
                    <input id="password" name="password" type="password" required>
                    @error('password')<div class="err-text">{{ $message }}</div>@enderror
                </div>
                <button type="submit" class="btn orange" style="width:100%; justify-content:center;">Sign in</button>
            </form>
        </div>
        <p class="muted" style="text-align:center; font-size:12px;">Need access? Contact your buyer at QuakeLogic.</p>
    </div>
@endsection
