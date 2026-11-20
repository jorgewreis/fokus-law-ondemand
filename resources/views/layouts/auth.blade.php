<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <meta name="referrer" content="no-referrer">
    <title>@yield('title') — Fokus Law</title>
    <style>
        :root { color-scheme: light; font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; color: #172b3a; background: #f3f6f8; font-synthesis: none; text-rendering: optimizeLegibility; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; }
        main { width: min(100% - 2rem, 42rem); margin: 0 auto; padding: clamp(1.5rem, 6vw, 4rem) 0; }
        main.wide { width: min(100% - 2rem, 76rem); }
        .brand { margin: 0 0 2rem; color: #315c70; font-size: .875rem; font-weight: 750; letter-spacing: .08em; text-transform: uppercase; }
        h1 { margin: 0 0 .75rem; font-size: clamp(1.8rem, 6vw, 2.5rem); line-height: 1.12; letter-spacing: -.035em; }
        h2 { margin: 0 0 .75rem; font-size: 1.25rem; }
        h3 { margin: 0 0 .5rem; font-size: 1.05rem; }
        p { line-height: 1.6; }
        a { color: #174f68; text-underline-offset: .18em; }
        a:hover { color: #0b3548; }
        a:focus-visible, button:focus-visible, input:focus-visible, select:focus-visible { outline: 3px solid #d28b00; outline-offset: 3px; }
        .panel, .notice, .status, .error-summary { margin: 1rem 0; padding: 1.15rem; border: 1px solid #d9e2e8; border-radius: .75rem; background: #fff; }
        .panel > :first-child { margin-top: 0; }
        .panel > :last-child { margin-bottom: 0; }
        .notice { border-color: #e8c977; background: #fff8e4; color: #533e0b; }
        .status { border-color: #83b99a; background: #edf7f1; }
        .error-summary, .field-error { border-color: #d8a1a1; background: #fff3f3; color: #8f1d1d; }
        .field { margin: 1rem 0; }
        label, legend { display: block; margin-bottom: .4rem; font-weight: 700; }
        input:not([type=checkbox]), select { width: 100%; min-height: 2.8rem; padding: .65rem .75rem; border: 1px solid #899aa5; border-radius: .4rem; background: #fff; color: inherit; font: inherit; }
        input[type=checkbox] { width: 1.2rem; height: 1.2rem; accent-color: #17485c; }
        .check { display: flex; gap: .65rem; align-items: flex-start; margin: .65rem 0; }
        .check label { margin: 0; font-weight: 500; line-height: 1.45; }
        button, .button { display: inline-flex; min-height: 2.75rem; align-items: center; justify-content: center; margin-top: .6rem; padding: .7rem 1rem; border: 0; border-radius: .4rem; background: #17485c; color: #fff; font: inherit; font-weight: 700; text-decoration: none; cursor: pointer; }
        button:hover, .button:hover { background: #103a4b; color: #fff; }
        button.secondary { border: 1px solid #8797a2; background: #fff; color: #172b3a; }
        button.danger { background: #8f2828; }
        .actions { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; }
        .muted { color: #526675; }
        .error-list { margin: .35rem 0 0; padding-left: 1.25rem; }
        .mono { overflow-wrap: anywhere; font-family: ui-monospace, SFMono-Regular, Consolas, monospace; }
        .grant-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(15rem, 1fr)); gap: .25rem 1rem; }
        fieldset { min-width: 0; margin: 1rem 0; padding: .9rem 1rem; border: 1px solid #d9e2e8; border-radius: .6rem; }
        .stack > * + * { margin-top: 1rem; }
        .top-links { display: flex; flex-wrap: wrap; gap: 1rem; margin: 0 0 1.5rem; }
        @media (max-width: 35rem) { main, main.wide { width: min(100% - 1.25rem, 76rem); } .panel, .notice, .status, .error-summary { padding: .9rem; } }
    </style>
</head>
<body>
<main @hasSection('wide') class="wide" @endif>
    <p class="brand"><a href="{{ route('home') }}">Fokus Cloud · Law</a></p>
    @if (session('status'))
        <p class="status" role="status" tabindex="-1">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <div class="error-summary" role="alert" aria-labelledby="error-title" tabindex="-1">
            <strong id="error-title">Revise os campos informados.</strong>
            <ul class="error-list">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    @yield('content')
</main>
</body>
</html>
