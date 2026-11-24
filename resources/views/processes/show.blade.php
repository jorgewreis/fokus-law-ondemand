@extends('layouts.auth')

@section('title', 'Processo')
@section('wide', 'true')

@section('content')
    <nav class="top-links" aria-label="Navegação de Processos">
        <a href="{{ route('processes.create') }}">Cadastrar Processo</a>
        <a href="{{ route('home') }}">Início</a>
    </nav>
    <h1>Processo</h1>
    <dl class="panel">
        <dt>CNJ</dt><dd class="mono">{{ $process->cnj_normalized }}</dd>
        <dt>Competência</dt><dd>{{ $process->competency_code }}</dd>
        <dt>Classificação de sigilo</dt><dd>CNJ DataJud {{ $process->secrecy_code }}</dd>
        <dt>Situação</dt><dd>{{ $process->operational_status }}</dd>
    </dl>
    @if ($canUpdate || $canArchive)
        <form method="post" action="{{ route('processes.status.update', $process->getKey()) }}" class="panel">
            @csrf @method('put')
            <div class="field">
                <label for="operational_status">Alterar situação</label>
                <select id="operational_status" name="operational_status" required>
                    @if ($canUpdate)
                        <option value="ativo" @selected($process->operational_status === 'ativo')>Ativo</option>
                        <option value="suspenso" @selected($process->operational_status === 'suspenso')>Suspenso</option>
                    @endif
                    @if ($canArchive)
                        <option value="arquivado_provisoriamente" @selected($process->operational_status === 'arquivado_provisoriamente')>Arquivado provisoriamente</option>
                        <option value="arquivado_definitivamente" @selected($process->operational_status === 'arquivado_definitivamente')>Arquivado definitivamente</option>
                    @endif
                </select>
            </div>
            <button type="submit">Salvar situação</button>
        </form>
    @endif
    @if ($people->isNotEmpty())
        <section aria-labelledby="linked-people-title">
            <h2 id="linked-people-title">Pessoas vinculadas</h2>
            @foreach ($people as $link)
                <article class="panel">
                    <p><a href="{{ route('people.show', $link->person) }}">{{ $link->person->name }}</a></p>
                    <p class="muted">{{ $link->role }}@if ($link->witness_origin) · {{ $link->witness_origin }}@endif</p>
                </article>
            @endforeach
        </section>
    @endif
@endsection
