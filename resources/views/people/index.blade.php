@extends('layouts.auth')

@section('title', 'Pessoas')
@section('wide', 'true')

@section('content')
    <nav class="top-links" aria-label="Navegação de Pessoas"><a href="{{ route('home') }}">Início</a></nav>
    <h1>Pessoas</h1>
    <div class="actions"><a class="button" href="{{ route('people.create') }}">Cadastrar Pessoa</a></div>

    <section class="panel" aria-labelledby="search-title">
        <h2 id="search-title">Consultar por nome</h2>
        <form method="post" action="{{ route('people.search') }}">
            @csrf
            <div class="field"><label for="person-search">Nome exato</label><input id="person-search" name="name" maxlength="255" autocomplete="off" required></div>
            <button type="submit">Consultar</button>
        </form>
    </section>

    <section aria-labelledby="results-title">
        <h2 id="results-title">{{ $searchPerformed ? 'Resultados da consulta' : 'Cadastros recentes' }}</h2>
        @forelse ($people as $person)
            <article class="panel">
                <h3>{{ $person->name }}</h3>
                <p>{{ $person->person_type === 'PF' ? 'Pessoa Física' : 'Pessoa Jurídica' }}</p>
                <a href="{{ route('people.show', $person) }}">Abrir cadastro</a>
            </article>
        @empty
            <p class="panel" role="status">{{ $searchPerformed ? 'Nenhuma Pessoa acessível corresponde exatamente à consulta.' : 'Nenhuma Pessoa cadastrada.' }}</p>
        @endforelse
        @if (! $searchPerformed && $hasMore)
            <p class="muted">A lista mostra os 100 cadastros mais recentes. Use a consulta pelo nome para localizar outra Pessoa.</p>
        @endif
    </section>
@endsection
