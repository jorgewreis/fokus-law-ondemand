<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="color-scheme" content="light">
        <title>Fokus Law — Ambiente sintético</title>
        <style>
            :root {
                color-scheme: light;
                font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
                color: #172b3a;
                background: #f3f6f8;
                font-synthesis: none;
                text-rendering: optimizeLegibility;
            }

            * { box-sizing: border-box; }
            body { margin: 0; min-height: 100vh; }
            main { width: min(100% - 2rem, 52rem); margin: 0 auto; padding: clamp(2rem, 8vw, 5rem) 0; }
            .brand { margin: 0 0 2.5rem; font-size: .9rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: #315c70; }
            .notice { display: flex; gap: .75rem; align-items: flex-start; padding: 1rem 1.125rem; border: 1px solid #e8c977; border-radius: .75rem; background: #fff8e4; color: #533e0b; }
            .notice strong { display: block; margin-bottom: .25rem; }
            .notice p { margin: 0; line-height: 1.5; }
            h1 { margin: 2rem 0 .75rem; font-size: clamp(2rem, 7vw, 3.5rem); line-height: 1.05; letter-spacing: -.04em; }
            .intro { max-width: 40rem; margin: 0; color: #526675; font-size: 1.125rem; line-height: 1.7; }
            .status { display: grid; grid-template-columns: auto 1fr; gap: 1rem; align-items: start; margin-top: 2rem; padding: 1.25rem; border: 1px solid #d9e2e8; border-radius: .75rem; background: #fff; }
            .status-mark { width: .75rem; height: .75rem; margin-top: .4rem; border-radius: 50%; background: #32728a; box-shadow: 0 0 0 .25rem #e4f0f4; }
            .status h2 { margin: 0 0 .35rem; font-size: 1rem; }
            .status p { margin: 0; color: #526675; line-height: 1.55; }
            footer { margin-top: 2rem; color: #657784; font-size: .875rem; }

            @media (max-width: 36rem) {
                main { width: min(100% - 1.25rem, 52rem); }
                .notice { font-size: .9375rem; }
            }
        </style>
    </head>
    <body>
        <main>
            <p class="brand">Fokus Cloud · Law</p>

            <aside class="notice" role="note" aria-label="Aviso de ambiente sintético">
                <span aria-hidden="true">⚠</span>
                <div>
                    <strong>Ambiente de desenvolvimento sintético</strong>
                    <p>Use somente dados fictícios. Não insira dados pessoais, processos reais ou informações do PJe.</p>
                </div>
            </aside>

            <h1>Uma base segura para começar.</h1>
            <p class="intro">O Fokus Law está sendo preparado para o fluxo sintético de Pessoas e Processos do Law on demand.</p>

            <section class="status" aria-labelledby="status-title">
                <span class="status-mark" aria-hidden="true"></span>
                <div>
                    <h2 id="status-title">Aplicação executável</h2>
                    <p>O fluxo de solicitação está disponível. A confirmação de e-mail é exercitada em testes simulados; o ambiente não entrega mensagens nem libera acesso funcional.</p>
                </div>
            </section>

            <nav aria-label="Acesso à aplicação">
                <p><a href="{{ route('login.create') }}">Entrar</a></p>
                @auth
                    @if (auth()->user()->is_synthetic_admin)
                        <p><a href="{{ route('account-admin.index') }}">Administrar contas sintéticas</a></p>
                    @endif
                @endauth
                <p><a href="{{ route('account-requests.create') }}">Solicitar acesso para avaliação</a></p>
            </nav>

            <footer>Dados de demonstração, quando habilitados, são identificados como sintéticos e só são semeados nos ambientes local e de teste.</footer>
        </main>
    </body>
</html>
