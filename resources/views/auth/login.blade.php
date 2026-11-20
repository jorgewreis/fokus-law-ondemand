@extends('layouts.auth')

@section('title', 'Entrar')

@section('content')
    <h1>Entrar</h1>
    <p class="muted">Use sua conta individual. O acesso aos módulos depende também das concessões explícitas.</p>
    <aside class="notice" role="note">Ambiente sintético: use somente dados fictícios. A confirmação do e-mail e a autenticação não concedem acesso a módulos por si sós.</aside>

    <form class="panel" method="post" action="{{ route('login.store') }}" autocomplete="on">
        @csrf
        <div class="field">
            <label for="email">E-mail</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" maxlength="254" required @if($errors->has('email')) aria-describedby="email-error" @endif>
            @error('email') <p class="field-error" id="email-error">{{ $message }}</p> @enderror
        </div>
        <div class="field">
            <label for="password">Senha</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required @if($errors->has('password')) aria-describedby="password-error" @endif>
            @error('password') <p class="field-error" id="password-error">{{ $message }}</p> @enderror
        </div>
        <div class="field">
            <label for="totp">Código do aplicativo autenticador</label>
            <input id="totp" name="totp" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" minlength="6" maxlength="6" @if($errors->has('totp')) aria-describedby="totp-help totp-error" @else aria-describedby="totp-help" @endif>
            <p class="muted" id="totp-help">Informe um código de seis dígitos ou use um código de recuperação abaixo.</p>
            @error('totp') <p class="field-error" id="totp-error">{{ $message }}</p> @enderror
        </div>
        <div class="field">
            <label for="recovery_code">Código de recuperação</label>
            <input id="recovery_code" name="recovery_code" autocomplete="one-time-code" autocapitalize="characters" spellcheck="false" maxlength="12" aria-describedby="recovery-error">
            @error('recovery_code') <p class="field-error" id="recovery-error">{{ $message }}</p> @enderror
        </div>
        <button type="submit">Entrar com MFA</button>
    </form>

    <p><a href="{{ route('account-password.request') }}">Esqueci minha senha</a></p>
    <p><a href="{{ route('account-recovery.create') }}">Perdi o autenticador e os códigos de recuperação</a></p>
    <p><a href="{{ route('account-requests.create') }}">Solicitar uma conta</a></p>
@endsection
