@extends('layouts.auth')

@section('title', 'Ativar conta')

@section('content')
    <h1>Ativar conta individual</h1>
    <p>Defina uma senha e configure seu aplicativo autenticador. O convite é de uso único e vence em 24 horas.</p>
    <aside class="notice" role="note">Ativar a conta não libera módulos. As concessões de acesso são administradas separadamente por Jorge.</aside>

    <form class="panel" method="post" action="{{ route('account-activation.store') }}" autocomplete="on">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div class="field">
            <label for="password">Nova senha</label>
            <input id="password" name="password" type="password" autocomplete="new-password" minlength="12" required aria-describedby="password-help">
            <p class="muted" id="password-help">Use uma senha única com pelo menos 12 caracteres.</p>
            @error('password') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div class="field">
            <label for="password_confirmation">Confirme a nova senha</label>
            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="12" required>
        </div>
        <button type="submit">Definir senha e configurar MFA</button>
    </form>
@endsection
