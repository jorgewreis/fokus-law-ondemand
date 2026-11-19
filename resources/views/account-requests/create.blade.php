<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Solicitar acesso — Fokus Law</title>
        <style>
            :root { color-scheme: light; font-family: Inter, ui-sans-serif, system-ui, sans-serif; color: #172b3a; background: #f3f6f8; }
            * { box-sizing: border-box; }
            body { margin: 0; min-height: 100vh; }
            main { width: min(100% - 2rem, 36rem); margin: 0 auto; padding: clamp(2rem, 8vw, 5rem) 0; }
            a { color: #315c70; }
            form, .notice, .status { margin-top: 1.5rem; padding: 1.25rem; border: 1px solid #d9e2e8; border-radius: .75rem; background: #fff; }
            label { display: block; margin: 1rem 0 .4rem; font-weight: 650; }
            input { width: 100%; min-height: 2.75rem; padding: .65rem .75rem; border: 1px solid #899aa5; border-radius: .4rem; font: inherit; }
            button { margin-top: 1.25rem; padding: .75rem 1rem; border: 0; border-radius: .4rem; background: #17485c; color: #fff; font: inherit; font-weight: 650; cursor: pointer; }
            .notice { background: #fff8e4; border-color: #e8c977; color: #533e0b; line-height: 1.55; }
            .status { background: #edf7f1; border-color: #83b99a; }
            .error { color: #9b1c1c; }
        </style>
    </head>
    <body>
        <main>
            <p><a href="{{ route('home') }}">Fokus Law</a></p>
            <h1>Solicitar acesso</h1>
            <p>Informe seu nome e um e-mail pessoal ou comum para iniciar a solicitação.</p>
            <aside class="notice">Ambiente sintético: use somente dados fictícios. A confirmação do e-mail não cria uma conta nem libera acesso. Toda solicitação depende de análise e aprovação do desenvolvedor.</aside>

            @if (session('status'))
                <p class="status" role="status">{{ session('status') }}</p>
            @endif

            <form method="post" action="{{ route('account-requests.store') }}">
                @csrf
                <label for="display_name">Nome</label>
                <input id="display_name" name="display_name" value="{{ old('display_name') }}" autocomplete="name" maxlength="120" required>
                @error('display_name') <p class="error">{{ $message }}</p> @enderror

                <label for="email">E-mail pessoal ou comum</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" maxlength="254" required>
                @error('email') <p class="error">{{ $message }}</p> @enderror

                <button type="submit">Enviar solicitação</button>
            </form>
        </main>
    </body>
</html>
