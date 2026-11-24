@extends('layouts.auth')

@section('title', 'Vínculos Conta–Pessoa')
@section('wide', 'true')

@section('content')
    <nav class="top-links" aria-label="Navegação administrativa">
        <a href="{{ route('account-admin.index') }}">Administração de contas</a>
        <a href="{{ route('home') }}">Início</a>
    </nav>
    <h1>Vínculos Conta–Pessoa</h1>
    <p class="muted">Somente Jorge pode criar o vínculo. A igualdade de e-mail não cria nem sugere uma associação. Desvinculação não está disponível nesta etapa.</p>
    @if ($people->isEmpty() || $users->isEmpty())
        <p class="panel">É necessário haver uma Pessoa e uma conta ativa sem vínculo para continuar.</p>
    @else
        <form method="post" action="{{ route('person-account-links.store') }}" class="stack">
            @csrf
            <div class="field">
                <label for="person_id">Pessoa</label>
                <select id="person_id" name="person_id" required>
                    <option value="">Selecione</option>
                    @foreach ($people as $person)
                        <option value="{{ $person->id }}">ID {{ $person->id }} · {{ $person->name }} · {{ $person->person_type }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="user_id">Conta ativa</label>
                <select id="user_id" name="user_id" required>
                    <option value="">Selecione</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}">ID {{ $user->id }} · {{ $user->name }} · {{ $user->email }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit">Vincular explicitamente</button>
        </form>
    @endif
@endsection
