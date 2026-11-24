@extends('layouts.auth')

@section('title', 'Cadastrar Processo')
@section('wide', 'true')

@section('content')
    <nav class="top-links" aria-label="Navegação de Processos">
        <a href="{{ route('home') }}">Início</a>
        @if ($canLinkPeople)<a href="{{ route('people.index') }}">Consultar Pessoas</a>@endif
    </nav>
    <h1>Cadastrar Processo</h1>
    @if (!$unit)
        <p class="notice" role="status">O cadastro está indisponível até a configuração da unidade do módulo.</p>
        @elseif (!$hasUnitGrant)
            <p class="notice" role="status">Sua conta não possui concessão explícita para a unidade configurada.</p>
    @else
        <p class="muted">Unidade configurada: {{ $unit->name }}. O Processo só ficará disponível para contas autorizadas após classificação de sigilo e concessão dos grants correspondentes.</p>
        @if ($competencies->isEmpty())
            <p class="notice" role="status">Não há competência ativa concedida à sua conta para esta unidade.</p>
        @else
            <form method="post" action="{{ route('processes.store') }}" class="stack">
                @csrf
                <div class="field">
                    <label for="cnj">Número CNJ</label>
                    <input id="cnj" name="cnj" inputmode="numeric" required maxlength="32" autocomplete="off" value="{{ old('cnj') }}">
                </div>
                <div class="field">
                    <label for="competency_code">Competência da Vara</label>
                    <select id="competency_code" name="competency_code" required>
                        <option value="">Selecione</option>
                        @foreach ($competencies as $competency)
                            <option value="{{ $competency['code'] }}" @selected((string) old('competency_code') === (string) $competency['code'])>{{ $competency['description'] }}</option>
                        @endforeach
                    </select>
                    <small>Sem classe, a competência deve ser escolhida explicitamente. Não é inferida do CNJ.</small>
                </div>
                @if ($canLinkPeople)
                    <fieldset>
                        <legend>Vincular Pessoa (opcional)</legend>
                        @if ($personId)
                            <input type="hidden" name="person_id" value="{{ old('person_id', $personId) }}">
                            <p>Pessoa identificada pelo ID interno {{ $personId }}.</p>
                        @else
                            <div class="field"><label for="person_id">ID interno da Pessoa</label><input id="person_id" name="person_id" inputmode="numeric" value="{{ old('person_id') }}"></div>
                        @endif
                        <div class="field">
                            <label for="role">Participação</label>
                            <select id="role" name="role">
                                <option value="">Selecione</option>
                                <option value="reu">Réu</option><option value="vitima">Vítima</option><option value="testemunha">Testemunha</option><option value="defensor">Defensor</option>
                            </select>
                        </div>
                        <div class="field">
                            <label for="witness_origin">Origem da testemunha</label>
                            <select id="witness_origin" name="witness_origin">
                                <option value="">Não se aplica</option><option value="denuncia">Denúncia</option><option value="defesa">Defesa</option><option value="ambas">Ambas</option>
                            </select>
                        </div>
                    </fieldset>
                @endif
                <button type="submit">Cadastrar Processo</button>
            </form>
        @endif
    @endif
@endsection
