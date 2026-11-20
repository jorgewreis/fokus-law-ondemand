@extends('layouts.auth')

@section('title', 'Redefinir senha')

@section('content')
    <h1>Redefinir senha</h1>
    <p>Informe o endereço de e-mail confirmado da sua conta. O link é de uso único e vence em 30 minutos.</p>
    <aside class="notice" role="note">Redefinir a senha não remove nem substitui o TOTP. O acesso continuará exigindo o aplicativo autenticador ou um código de recuperação não utilizado.</aside>

    <form class="panel" method="post" action="{{ route('account-password.email') }}" autocomplete="on">
        @csrf
        <div class="field">
            <label for="email">E-mail da conta</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" maxlength="254" required>
            @error('email') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <button type="submit">Enviar link de redefinição</button>
    </form>
    <p><a href="{{ route('login.create') }}">Voltar para entrar</a></p>
@endsection
