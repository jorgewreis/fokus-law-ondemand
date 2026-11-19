<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Confirmar e-mail — Fokus Law</title>
        <style>
            :root { color-scheme: light; font-family: Inter, ui-sans-serif, system-ui, sans-serif; color: #172b3a; background: #f3f6f8; }
            * { box-sizing: border-box; }
            body { margin: 0; min-height: 100vh; }
            main { width: min(100% - 2rem, 36rem); margin: 0 auto; padding: clamp(2rem, 8vw, 5rem) 0; }
            article { margin-top: 1.5rem; padding: 1.25rem; border: 1px solid #d9e2e8; border-radius: .75rem; background: #fff; line-height: 1.6; }
            button { margin-top: 1rem; padding: .75rem 1rem; border: 0; border-radius: .4rem; background: #17485c; color: #fff; font: inherit; font-weight: 650; cursor: pointer; }
        </style>
    </head>
    <body>
        <main>
            <h1>Confirmar endereço de e-mail</h1>
            <article>
                <p>Confirme que você controla o endereço informado para a solicitação sintética de {{ $accountRequest->display_name }}.</p>
                <p>Esta etapa não cria uma conta nem libera acesso. Após confirmar, a solicitação aguardará análise do desenvolvedor.</p>
                <form method="post" action="{{ $confirmationUrl }}">
                    @csrf
                    <button type="submit">Confirmar meu e-mail</button>
                </form>
            </article>
        </main>
    </body>
</html>
