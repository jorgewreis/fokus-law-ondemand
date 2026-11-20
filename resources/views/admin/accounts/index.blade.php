@extends('layouts.auth')

@section('title', 'Administração de contas')
@section('wide', 'true')

@section('content')
    <nav class="top-links" aria-label="Navegação da conta">
        <a href="{{ route('home') }}">Início</a>
        <a href="{{ route('competency-admin.index') }}">Competências</a>
        <form method="post" action="{{ route('logout') }}">
            @csrf
            <button class="secondary" type="submit">Sair</button>
        </form>
    </nav>
    <h1>Administração de contas sintéticas</h1>
    <p>Somente o administrador sintético pode aprovar solicitações e alterar concessões. Aprovação, ativação, login e MFA não concedem ações de módulo automaticamente.</p>
    <aside class="notice" role="note">Use somente contas sintéticas. As concessões listadas controlam apenas os módulos e as ações selecionados; unidade, competência e sigilo continuam sendo verificações separadas.</aside>

    <section aria-labelledby="requests-title">
        <h2 id="requests-title">Solicitações aguardando aprovação</h2>
        @forelse ($requests as $accountRequest)
            <article class="panel" aria-labelledby="request-{{ $accountRequest->id }}">
                <h3 id="request-{{ $accountRequest->id }}">Solicitação {{ $accountRequest->id }}</h3>
                <p><strong>Nome:</strong> {{ $accountRequest->display_name }}</p>
                <p><strong>E-mail confirmado:</strong> {{ $accountRequest->email }}</p>
                <p class="muted">Recebida em {{ $accountRequest->submitted_at?->format('d/m/Y H:i') }}</p>
                <div class="actions">
                    <form method="post" action="{{ route('account-admin.approve', $accountRequest) }}">
                        @csrf
                        <button type="submit">Aprovar e emitir convite de ativação</button>
                    </form>
                    <form method="post" action="{{ route('account-admin.reject', $accountRequest) }}">
                        @csrf
                        <button class="danger" type="submit">Rejeitar solicitação</button>
                    </form>
                </div>
            </article>
        @empty
            <p class="panel">Não há solicitações pendentes.</p>
        @endforelse
    </section>

    <section aria-labelledby="users-title">
        <h2 id="users-title">Contas e concessões</h2>
        @forelse ($users as $user)
            <article class="panel" aria-labelledby="user-{{ $user->id }}">
                <h3 id="user-{{ $user->id }}">{{ $user->email }}</h3>
                <p>Estado da conta: <strong>{{ $user->is_active ? 'Ativa' : 'Pendente de ativação' }}</strong></p>
                @if (! $user->is_active)
                    <form method="post" action="{{ route('account-admin.reissue', $user) }}">
                        @csrf
                        <button class="secondary" type="submit">Emitir novo convite de 24 horas</button>
                    </form>
                @endif

                <form method="post" action="{{ route('account-admin.grants', $user) }}">
                    @csrf
                    <fieldset>
                        <legend>Concessões explícitas</legend>
                        <div class="grant-grid">
                            @foreach ($modules as $moduleKey => $moduleLabel)
                                @foreach ($grantActions[$moduleKey] as $actionKey)
                                    @php($actionLabel = $actions[$actionKey])
                                    @php($grantValue = $moduleKey.'.'.$actionKey)
                                    <div class="check">
                                        <input id="grant-{{ $user->id }}-{{ $moduleKey }}-{{ $actionKey }}" type="checkbox" name="grants[]" value="{{ $grantValue }}" @checked($user->grants->contains(fn ($grant) => $grant->module === $moduleKey && $grant->action === $actionKey))>
                                        <label for="grant-{{ $user->id }}-{{ $moduleKey }}-{{ $actionKey }}">{{ $moduleLabel }} — {{ $actionLabel }}</label>
                                    </div>
                                @endforeach
                            @endforeach
                        </div>
                    </fieldset>
                    <button type="submit">Salvar concessões selecionadas</button>
                </form>
            </article>
        @empty
            <p class="panel">Ainda não há contas cadastradas.</p>
        @endforelse
    </section>

    <section aria-labelledby="recovery-title">
        <h2 id="recovery-title">Pedidos de recuperação de MFA</h2>
        @forelse ($recoveryRequests as $recoveryRequest)
            <article class="panel" aria-labelledby="recovery-{{ $recoveryRequest->id }}">
                <h3 id="recovery-{{ $recoveryRequest->id }}">Pedido {{ $recoveryRequest->id }} — {{ $recoveryRequest->user->email }}</h3>
                <p>Recebido em {{ $recoveryRequest->requested_at?->format('d/m/Y H:i') }}.</p>
                <form method="post" action="{{ route('mfa-recovery.approve', $recoveryRequest) }}">
                    @csrf
                    <div class="check">
                        <input id="known-channel-{{ $recoveryRequest->id }}" name="confirmed_by_known_channel" type="checkbox" value="1" required>
                        <label for="known-channel-{{ $recoveryRequest->id }}">Confirmei a identidade da pessoa por um canal de contato previamente conhecido.</label>
                    </div>
                    <p class="muted">A aprovação revoga o TOTP e os códigos atuais. A pessoa terá de cadastrar um novo autenticador antes de voltar a entrar.</p>
                    <button class="danger" type="submit">Confirmar recuperação e exigir novo MFA</button>
                </form>
            </article>
        @empty
            <p class="panel">Não há pedidos de recuperação pendentes.</p>
        @endforelse
    </section>
@endsection
