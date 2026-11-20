@extends('layouts.auth')

@section('title', 'Escolher nova senha')

@section('content')
    <h1>Escolher nova senha</h1>
    <p>Use um link ainda válido e defina uma senha única para sua conta.</p>
    <aside class="notice" role="note">O link vence em 30 minutos e só pode ser usado uma vez. A redefinição não altera o TOTP nem as concessões de acesso.</aside>

    <form class="panel" method="post" action="{{ route('account-password.update') }}" autocomplete="on">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div class="field">
            <label for="email">E-mail da conta</label>
            <input id="email" name="email" type="email" value="{{ old('email', $email) }}" autocomplete="username" maxlength="254" required>
            @error('email') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div class="field">
            <label for="password">Nova senha</label>
            <input id="password" name="password" type="password" autocomplete="new-password" minlength="12" required>
            @error('password') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div class="field">
            <label for="password_confirmation">Confirme a nova senha</label>
            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="12" required>
        </div>
        <button type="submit">Salvar nova senha</button>
    </form>
@endsection
