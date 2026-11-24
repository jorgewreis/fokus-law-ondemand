@extends('layouts.auth')

@section('title', 'Cadastro próprio não vinculado')
@section('content')
    <nav class="top-links" aria-label="Navegação de contato próprio"><a href="{{ route('home') }}">Início</a></nav>
    <h1>Cadastro próprio indisponível</h1>
    <p class="panel" role="status">Sua conta ainda não tem um cadastro de Pessoa vinculado. O vínculo deve ser feito explicitamente por Jorge.</p>
@endsection
