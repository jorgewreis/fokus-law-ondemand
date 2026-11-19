<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="color-scheme" content="light">
        <title>Contatos institucionais — Fokus Law</title>
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
            h1 { max-width: 12ch; margin: 0 0 1rem; font-size: clamp(2.25rem, 8vw, 4rem); line-height: 1.02; letter-spacing: -.045em; }
            .intro { max-width: 38rem; margin: 0 0 2.5rem; color: #526675; font-size: 1.125rem; line-height: 1.65; }
            dl { display: grid; grid-template-columns: 1fr 1fr; margin: 0; border-top: 1px solid #cbd7df; }
            .contact { padding: 1.25rem 1rem 1.25rem 0; border-bottom: 1px solid #cbd7df; }
            .contact:last-child { grid-column: 1 / -1; }
            dt { margin-bottom: .5rem; color: #526675; font-size: .9rem; font-weight: 650; }
            dd { margin: 0; font-size: 1.05rem; line-height: 1.6; overflow-wrap: anywhere; }
            a { color: #155d76; text-underline-offset: .2em; }
            a:focus-visible { outline: 3px solid #a24c1d; outline-offset: 3px; border-radius: 2px; }
            .pending { color: #533e0b; }
            footer { margin-top: 2rem; color: #526675; font-size: .875rem; line-height: 1.6; }

            @media (max-width: 36rem) {
                main { width: min(100% - 1.25rem, 52rem); }
                dl { grid-template-columns: 1fr; }
                .contact:last-child { grid-column: auto; }
            }
        </style>
    </head>
    <body>
        <main>
            <p class="brand">Fokus Cloud · Law</p>
            <h1>Contatos institucionais</h1>
            <p class="intro">Cliente institucional on demand. Esta página reúne canais públicos de demonstração, com dados sintéticos.</p>

            <dl>
                <div class="contact">
                    <dt>Telefones</dt>
                    <dd>
                        <ul>
                            @foreach ($contacts['phones'] as $phone)
                                <li>{{ $phone['label'] }}: <a href="tel:{{ $phone['tel'] }}">{{ $phone['display'] }}</a></li>
                            @endforeach
                        </ul>
                    </dd>
                </div>
                <div class="contact">
                    <dt>WhatsApp institucional</dt>
                    <dd><a href="{{ $contacts['whatsapp']['url'] }}" rel="noopener noreferrer">{{ $contacts['whatsapp']['display'] }}</a></dd>
                </div>
                <div class="contact">
                    <dt>E-mail institucional</dt>
                    <dd><a href="mailto:{{ $contacts['email'] }}">{{ $contacts['email'] }}</a></dd>
                </div>
                <div class="contact">
                    <dt>Endereço</dt>
                    <dd>{{ $contacts['address'] }}</dd>
                </div>
                <div class="contact">
                    <dt>Horário de expediente</dt>
                    <dd class="pending">Não informado na fonte oficial consultada; aguarda confirmação.</dd>
                </div>
            </dl>

            <footer>
                Fonte: <a href="{{ $contacts['source'] }}" rel="noopener noreferrer">Contatos do cliente institucional on demand</a>.
                Conferido em {{ $contacts['checkedAt'] }}. O horário permanece pendente porque não consta nessa ficha oficial.
            </footer>
        </main>
    </body>
</html>
