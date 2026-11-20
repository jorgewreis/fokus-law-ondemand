@extends('layouts.auth')

@section('title', 'Códigos de recuperação')

@section('content')
    <h1>Guarde seus códigos de recuperação</h1>
    <aside class="notice" role="alert"><strong>Esta é a única exibição dos códigos em texto.</strong> Cada código pode ser usado uma única vez. Guarde-os em local seguro e não os envie por e-mail ou mensagem.</aside>
    <section class="panel" aria-label="Códigos de recuperação">
        <ul class="mono">
            @foreach ($recoveryCodes as $recoveryCode)
                <li>{{ $recoveryCode }}</li>
            @endforeach
        </ul>
    </section>
    <p>Depois de salvar os códigos, entre com sua senha e um código TOTP. Concessões de módulos continuam sendo uma etapa separada.</p>
    <a class="button" href="{{ route('login.create') }}">Ir para entrar</a>
@endsection
