@extends('layouts.auth')

@section('title', 'Configurar MFA')

@section('content')
    <h1>Configurar aplicativo autenticador</h1>
    <p>Adicione esta conta a um aplicativo autenticador. Se preferir, informe manualmente a chave abaixo.</p>
    <aside class="notice" role="note">A configuração do TOTP habilita a autenticação multifator, mas não concede acesso a nenhum módulo.</aside>

    <section class="panel" aria-labelledby="setup-title">
        <h2 id="setup-title">Configuração do autenticador</h2>
        <p><strong>Conta:</strong> {{ $email }}</p>
        <p><strong>Emissor:</strong> {{ $issuer }}</p>
        <p><strong>Chave de configuração:</strong></p>
        <p class="mono" tabindex="0">{{ $secret }}</p>
        <p><a class="button" href="{{ $provisioningUri }}">Abrir no aplicativo autenticador</a></p>
        <p class="muted">Não compartilhe a chave nem a inclua em mensagens ou capturas de tela.</p>
    </section>

    <form class="panel" method="post" action="{{ route('mfa-enrollment.store') }}" autocomplete="off">
        @csrf
        <div class="field">
            <label for="code">Código de seis dígitos</label>
            <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" minlength="6" maxlength="6" required>
            @error('code') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <button type="submit">Confirmar e habilitar MFA</button>
    </form>
@endsection
