@extends('layouts.auth')

@section('title', 'Concessões de sigilo')
@section('wide', 'true')

@section('content')
    <nav class="top-links" aria-label="Navegação administrativa">
        <a href="{{ route('account-admin.index') }}">Administração de contas</a>
        <a href="{{ route('competency-admin.index') }}">Competências</a>
        <a href="{{ route('home') }}">Início</a>
    </nav>
    <h1>Concessões de sigilo sintético</h1>
    <p>Somente Jorge pode administrar estas concessões. O código DataJud é um rótulo de classificação sintética, não um papel ou uma hierarquia de acesso no Fokus. Cada conta precisa do grant exato do código do Processo; os demais gates continuam independentes.</p>
    @if (! $catalogComplete)
        <p class="notice" role="status">O catálogo está incompleto ou inválido. Novas concessões ficam indisponíveis; concessões existentes ainda podem ser revogadas.</p>
    @endif
    @foreach ($users as $user)
        <article class="panel" aria-labelledby="user-{{ $user->id }}">
            <h2 id="user-{{ $user->id }}">{{ $user->email }}</h2>
            <p class="muted">Conta {{ $user->is_active ? 'ativa' : 'pendente de ativação' }}.</p>
            <h3>Códigos concedidos</h3>
            @forelse ($user->secrecyGrants as $grant)
                <form method="post" action="{{ route('secrecy-admin.grant', $user) }}" class="actions">
                    @csrf
                    <input type="hidden" name="secrecy" value="{{ $grant->secrecy_code }}">
                    <input type="hidden" name="operation" value="revoke">
                    <span>CNJ DataJud {{ $grant->secrecy_code }}</span>
                    <button class="secondary" type="submit">Revogar</button>
                </form>
            @empty
                <p>Nenhum código concedido.</p>
            @endforelse
            @if ($catalogComplete)
                <form method="post" action="{{ route('secrecy-admin.grant', $user) }}">
                    @csrf
                    <input type="hidden" name="operation" value="grant">
                    <div class="field">
                        <label for="secrecy-{{ $user->id }}">Conceder código DataJud</label>
                        <select id="secrecy-{{ $user->id }}" name="secrecy" required>
                            <option value="">Selecione um código</option>
                            @foreach ($codes as $code)
                                <option value="{{ $code->code }}">CNJ DataJud {{ $code->code }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit">Conceder código</button>
                </form>
            @endif
        </article>
    @endforeach
    @if ($users->isEmpty())
        <p class="panel">Ainda não há contas para receber concessões.</p>
    @endif
@endsection
