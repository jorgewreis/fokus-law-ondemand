@extends('layouts.auth')

@section('title', 'Competências da Vara')
@section('wide', 'true')

@section('content')
    <nav class="top-links" aria-label="Navegação administrativa">
        <a href="{{ route('account-admin.index') }}">Administração de contas</a>
        <a href="{{ route('home') }}">Início</a>
    </nav>
    <h1>Competências da Vara</h1>
    <p>Configure as competências e conceda acesso às contas sintéticas. Cada conta precisa de uma concessão explícita para a competência do Processo.</p>
    <aside class="notice" role="note">Justiça Estadual · 1º grau. A configuração de classes e os demais controles de acesso ainda precisam ser concluídos antes do uso de Processos.</aside>

    <section class="panel" aria-labelledby="catalog-title">
        <h2 id="catalog-title">Catálogo de referência</h2>
        @if ($catalogEmpty)
            <p>O catálogo de competências ainda não foi carregado. Carregue a referência do CNJ para começar.</p>
        @elseif (! $catalogComplete)
            <p role="status">O catálogo está incompleto ou inválido. Carregue a referência para disponibilizar os itens ausentes. A configuração e novas concessões ficam indisponíveis até a correção.</p>
        @else
            <p>Catálogo de referência carregado.</p>
        @endif
        <form method="post" action="{{ route('competency-admin.import') }}">
            @csrf
            <button class="secondary" type="submit">Carregar catálogo</button>
        </form>
    </section>
    @if ($catalogComplete)
        <section class="panel" aria-labelledby="settings-title">
            <h2 id="settings-title">Competências configuradas</h2>
            <p>Selecione uma ou mais competências. Retirar uma competência impede novos usos; os registros existentes mantêm sua classificação.</p>
            <form method="post" action="{{ route('competency-admin.settings') }}">
                @csrf
                <input type="hidden" name="revision" value="{{ $revision }}">
                @php($selectedCompetencies = $errors->has('revision') ? ($settings['competencies'] ?? []) : old('competencies', $settings['competencies'] ?? []))
                @php($selectedCompetencies = is_array($selectedCompetencies) ? array_map('strval', array_filter($selectedCompetencies, 'is_scalar')) : [])
                <fieldset>
                    <legend>Competências da Vara</legend>
                    <div class="grant-grid">
                        @foreach ($competencies as $competency)
                            <div class="check">
                                <input id="setting-{{ $competency->code }}" name="competencies[]" type="checkbox" value="{{ $competency->code }}" @checked(in_array((string) $competency->code, $selectedCompetencies, true))>
                                <label for="setting-{{ $competency->code }}">{{ $competency->description }} ({{ $competency->code }})</label>
                            </div>
                        @endforeach
                    </div>
                </fieldset>
                <button type="submit">Salvar competências da Vara</button>
            </form>
        </section>

    @endif
        <section aria-labelledby="grants-title">
            <h2 id="grants-title">Concessões por conta</h2>
            @forelse ($users as $user)
                <article class="panel" aria-labelledby="competency-user-{{ $user->id }}">
                    <h3 id="competency-user-{{ $user->id }}">{{ $user->email }}</h3>
                    <p class="muted">Conta {{ $user->is_active ? 'ativa' : 'pendente de ativação' }}.</p>
                    <h4>Competências concedidas</h4>
                    @forelse ($grants->get($user->id, collect()) as $grant)
                        <form method="post" action="{{ route('competency-admin.grant', $user) }}" class="actions">
                            @csrf
                            <input type="hidden" name="competency" value="{{ $grant->code }}">
                            <input type="hidden" name="operation" value="revoke">
                            <span>{{ $grant->description }} ({{ $grant->code }})</span>
                            <button class="secondary" type="submit" aria-label="Revogar {{ $grant->description }} de {{ $user->email }}">Revogar</button>
                        </form>
                    @empty
                        <p>Nenhuma competência concedida.</p>
                    @endforelse
                    @if ($catalogComplete)
                    <form method="post" action="{{ route('competency-admin.grant', $user) }}">
                        @csrf
                        <input type="hidden" name="operation" value="grant">
                        <div class="field">
                            <label for="competency-{{ $user->id }}">Conceder competência</label>
                            <select id="competency-{{ $user->id }}" name="competency" required>
                                <option value="">Selecione uma competência</option>
                                @foreach ($competencies as $competency)
                                    <option value="{{ $competency->code }}">{{ $competency->description }} ({{ $competency->code }})</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit">Conceder competência</button>
                    </form>
                    @endif
                </article>
            @empty
                <p class="panel">Ainda não há contas para receber concessões.</p>
            @endforelse
        </section>
@endsection
