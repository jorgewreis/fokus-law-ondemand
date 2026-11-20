@extends('layouts.auth')

@section('title', 'Pessoa')
@section('wide', 'true')

@section('content')
    <nav class="top-links" aria-label="Navegação de Pessoas"><a href="{{ route('people.index') }}">Consultar Pessoas</a><a href="{{ route('home') }}">Início</a></nav>
    <h1>{{ $person->name }}</h1>
    <p><strong>{{ $person->person_type === 'PF' ? 'Pessoa Física' : 'Pessoa Jurídica' }}</strong> · Identificador interno {{ $person->getKey() }}</p>
    @if ($canEdit)
        <p><a class="button" href="{{ route('people.edit', $person) }}">Editar Pessoa</a></p>
    @endif
    @if ($canCreateProcess)
        <p><a class="button secondary" href="{{ route('processes.create', ['person_id' => $person->getKey()]) }}">Cadastrar Processo e vincular esta Pessoa</a></p>
    @endif
    @if ($canDelete)
        <form method="post" action="{{ route('people.destroy', $person) }}" onsubmit="return confirm('Remover esta Pessoa do cadastro operacional?');">
            @csrf
            @method('delete')
            <button class="danger" type="submit">Excluir Pessoa</button>
        </form>
    @endif

    <section class="panel" aria-labelledby="address-title">
        <h2 id="address-title">Endereço</h2>
        <p>{{ $person->street }}, {{ $person->address_number }} @if ($person->complement) , {{ $person->complement }} @endif </p>
        <p>@if ($person->district) {{ $person->district }} · @endif {{ $person->city }}/{{ $person->state }} · CEP {{ substr($person->postal_code, 0, 5) }}-{{ substr($person->postal_code, 5, 3) }}</p>
    </section>

    <section class="panel" aria-labelledby="phones-title">
        <h2 id="phones-title">Telefones</h2>
        @forelse ($person->phones as $phone)
            <p>{{ app(\App\Services\BrazilianPhoneNumber::class)->format($phone->number) }} — {{ $phone->category }} · {{ app(\App\Services\BrazilianPhoneNumber::class)->stateLabel($phone->number) }} @if ($phone->is_primary) — principal @endif </p>
        @empty
            <p>Nenhum telefone cadastrado.</p>
        @endforelse
    </section>

    <section class="panel" aria-labelledby="documents-title">
        <h2 id="documents-title">Documentos</h2>
        @forelse ($person->documents as $document)
            <p>{{ $document->type }} — {{ $document->number }} @if ($document->issuing_authority) · {{ $document->issuing_authority }} @endif @if ($document->issuing_state) / {{ $document->issuing_state }} @endif </p>
        @empty
            <p>Nenhum documento cadastrado.</p>
        @endforelse
    </section>

    <section class="panel" aria-labelledby="whatsapp-title">
        <h2 id="whatsapp-title">Identificadores WhatsApp</h2>
        @forelse ($person->whatsappIdentifiers as $identifier)
            <p>{{ $identifier->type === 'phone' ? app(\App\Services\BrazilianPhoneNumber::class)->format($identifier->value) : $identifier->value }} @if ($identifier->user_key) · chave de usuário registrada @endif @if ($identifier->is_primary) · principal @endif </p>
        @empty
            <p>Nenhum identificador cadastrado.</p>
        @endforelse
    </section>
@endsection
