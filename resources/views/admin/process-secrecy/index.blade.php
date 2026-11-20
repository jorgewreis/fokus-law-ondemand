@extends('layouts.auth')

@section('title', 'Classificação de sigilo')
@section('wide', 'true')

@section('content')
    <nav class="top-links" aria-label="Navegação administrativa">
        <a href="{{ route('account-admin.index') }}">Administração de contas</a>
        <a href="{{ route('secrecy-admin.index') }}">Grants de sigilo</a>
        <a href="{{ route('home') }}">Início</a>
    </nav>
    <h1>Classificação de sigilo</h1>
    <p class="muted">Esta tela exibe somente IDs internos. Atribuir uma classificação não concede acesso ao conteúdo do Processo.</p>
    @forelse ($processes as $process)
        <article class="panel" aria-labelledby="process-{{ $process->id }}">
            <h2 id="process-{{ $process->id }}">ID interno {{ $process->id }}</h2>
            <p class="muted">Competência {{ $process->competency_code ?? 'pendente' }} · Classe {{ $process->class_code ?? 'não informada' }}</p>
            <form method="post" action="{{ route('process-secrecy-admin.update', $process->id) }}" class="stack">
                @csrf @method('put')
                <div class="field">
                    <label for="secrecy-{{ $process->id }}">Classificação</label>
                    <select id="secrecy-{{ $process->id }}" name="secrecy_code" required>
                        @foreach ($codes as $code)
                            <option value="{{ $code->code }}" @selected((int) ($process->secrecy_code ?? -1) === (int) $code->code)>{{ $code->label }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit">Atribuir ou alterar classificação</button>
            </form>
        </article>
    @empty
        <p class="panel">Não há Processos cadastrados.</p>
    @endforelse
@endsection
