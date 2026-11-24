@extends('layouts.auth')

@section('title', 'Unidade do módulo')
@section('wide', 'true')

@section('content')
    <nav class="top-links" aria-label="Navegação administrativa">
        <a href="{{ route('account-admin.index') }}">Administração de contas</a>
        <a href="{{ route('competency-admin.index') }}">Competências</a>
        <a href="{{ route('secrecy-admin.index') }}">Sigilo</a>
        <a href="{{ route('home') }}">Início</a>
    </nav>
    <h1>Unidade de Gestão de Processos</h1>
    <p>O módulo usa uma Unidade configurada explicitamente. Cada Processo deverá guardar sua própria referência à Unidade; trocar a configuração não move registros existentes. Grant de Unidade é separado de competência, sigilo e permissões de módulo.</p>

    <section class="panel" aria-labelledby="setting-title">
        <h2 id="setting-title">Configuração atual</h2>
        @if ($unit)
            <p><strong>{{ $unit->name }}</strong> <span class="muted">({{ $unit->id }})</span></p>
        @else
            <p role="status">Nenhuma Unidade configurada. O cadastro de Processos deve permanecer bloqueado.</p>
        @endif
        <form method="post" action="{{ route('unit-admin.configure') }}">
            @csrf
            <input type="hidden" name="revision" value="{{ $revision }}">
            <div class="field">
                <label for="unit-name">Configurar Unidade</label>
                <input id="unit-name" name="unit" maxlength="160" value="{{ old('unit', $unit->name ?? '1ª Vara Crime de Ilhéus') }}" required>
            </div>
            <button type="submit">Salvar Unidade</button>
        </form>
        @if ($units->isNotEmpty())
            <h3>Unidades registradas</h3>
            <ul>
                @foreach ($units as $registeredUnit)
                    <li>{{ $registeredUnit->name }} — {{ $registeredUnit->id }}</li>
                @endforeach
            </ul>
        @endif
    </section>

    <section aria-labelledby="grants-title">
        <h2 id="grants-title">Grants de Unidade por conta</h2>
        @forelse ($users as $user)
            <article class="panel" aria-labelledby="user-{{ $user->id }}">
                <h3 id="user-{{ $user->id }}">{{ $user->email }}</h3>
                <p class="muted">Conta {{ $user->is_active ? 'ativa' : 'pendente de ativação' }}.</p>
                @forelse ($user->unitGrants as $grant)
                    <form method="post" action="{{ route('unit-admin.grant', $user) }}" class="actions">
                        @csrf
                        <input type="hidden" name="unit" value="{{ $grant->unit_id }}">
                        <input type="hidden" name="operation" value="revoke">
                        <span>{{ $grant->unit?->name ?? 'Unidade histórica' }}</span>
                        <button class="secondary" type="submit">Revogar</button>
                    </form>
                @empty
                    <p>Nenhum grant de Unidade.</p>
                @endforelse
                @if ($units->isNotEmpty())
                    <form method="post" action="{{ route('unit-admin.grant', $user) }}">
                        @csrf
                        <input type="hidden" name="operation" value="grant">
                        <div class="field">
                            <label for="grant-unit-{{ $user->id }}">Conceder Unidade</label>
                            <select id="grant-unit-{{ $user->id }}" name="unit" required>
                                <option value="">Selecione uma Unidade</option>
                                @foreach ($units as $registeredUnit)
                                    <option value="{{ $registeredUnit->id }}">{{ $registeredUnit->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit">Conceder Unidade</button>
                    </form>
                @endif
            </article>
        @empty
            <p class="panel">Ainda não há contas para receber grants.</p>
        @endforelse
    </section>
@endsection
