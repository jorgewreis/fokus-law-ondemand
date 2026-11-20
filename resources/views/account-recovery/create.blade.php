@extends('layouts.auth')

@section('title', 'Recuperação de acesso')

@section('content')
    <h1>Solicitar recuperação de MFA</h1>
    <p>Use esta opção somente se perdeu o autenticador e todos os códigos de recuperação. Informe sua senha para abrir uma solicitação restrita, sem iniciar sessão.</p>
    <aside class="notice" role="note">Jorge precisa confirmar sua identidade por um canal de contato previamente conhecido antes de aprovar a recuperação. A senha e esta solicitação não restabelecem o acesso.</aside>

    <form class="panel" method="post" action="{{ route('account-recovery.store') }}" autocomplete="on">
        @csrf
        <div class="field">
            <label for="email">E-mail da conta</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" maxlength="254" required>
            @error('email') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div class="field">
            <label for="password">Senha atual</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required>
            @error('password') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <button type="submit">Enviar solicitação de recuperação</button>
    </form>
    <p><a href="{{ route('login.create') }}">Voltar para entrar</a></p>
@endsection
