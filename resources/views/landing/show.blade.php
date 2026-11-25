<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">
        <meta name="color-scheme" content="dark">
        <title>Fokus Law on demand — A pauta do dia</title>
        @vite(['resources/css/landing.css', 'resources/js/landing-motion.js'])
    </head>
    <body class="landing">
        <a class="skip" href="#pauta">Ir para a pauta de hoje</a>
        <div class="page">
            <header class="nav">
                <a class="brand" href="{{ route('home') }}">
                    <span class="mark">Fokus Law</span>
                    <span class="sub">On demand</span>
                </a>
                <nav class="navlinks" aria-label="Nesta página">
                    <a href="#unidade" aria-current="page">A unidade</a>
                    <a href="#pauta">Pauta do dia</a>
                    <a href="#produtividade">Produtividade</a>
                    <a href="#plantao">Plantão</a>
                </nav>
                <span class="pill">Ambiente isolado · exemplo</span>
            </header>

            <section class="hero" aria-labelledby="page-title">
                <img class="hero-media" src="{{ asset('images/hero-courthouse.jpg') }}" alt="">
                <div class="hero-in">
                    <div class="hero-copy">
                        <p class="eyebrow">{{ $board['date_label'] }}</p>
                        <h1 class="big" id="page-title">A pauta do dia,<br><em>em um só lugar.</em></h1>
                        <p class="lede">O novo Fokus Law on demand da 1ª Vara Crime de Ilhéus — acesso, pauta, audiências e produtividade da unidade, num ambiente novo e separado do atual.</p>
                        <dl class="unit" id="unidade">
                            <div>
                                <dt>Unidade</dt>
                                <dd>{{ $board['unit']['name'] }}</dd>
                            </div>
                            <div>
                                <dt>Tribunal</dt>
                                <dd>{{ $board['unit']['court'] }}</dd>
                            </div>
                            <div>
                                <dt>Comarca</dt>
                                <dd>{{ $board['unit']['district'] }}</dd>
                            </div>
                            <div>
                                <dt>{{ $board['unit']['address_label'] }}</dt>
                                <dd>{{ $board['unit']['address'] }}</dd>
                            </div>
                            <div>
                                <dt>{{ $board['unit']['phone_label'] }}</dt>
                                <dd>{{ $board['unit']['phone'] }}</dd>
                            </div>
                            <div>
                                <dt>{{ $board['unit']['hours_label'] }}</dt>
                                <dd>{{ $board['unit']['hours'] }}</dd>
                            </div>
                        </dl>
                    </div>

                    <div class="glass-shell">
                    <aside class="glass" aria-label="Área de acesso" data-lenis-prevent>
                        @if (session('status'))
                            <p class="status" role="status">{{ session('status') }}</p>
                        @endif

                        @guest
                            <nav class="tabs" aria-label="Formulários de acesso">
                                <a class="tab-label tab-label-entrar @if(! $cadastroAtivo) is-active @endif" href="#acesso-entrar">Entrar</a>
                                <a class="tab-label tab-label-cadastro @if($cadastroAtivo) is-active @endif" href="#acesso-cadastro">Solicitar cadastro</a>
                            </nav>

                            <div class="panel-login" id="acesso-entrar">
                                <p class="kicker">Área de acesso</p>
                                <h2>Entrar no Fokus Law</h2>
                                <p class="hint">E-mail institucional ou matrícula funcional.</p>
                                <form method="post" action="{{ route('login.store') }}" data-lenis-prevent autocomplete="on">
                                    @csrf
                                    <input type="hidden" name="form" value="login">
                                    <label class="fld" for="identifier">E-mail ou matrícula</label>
                                    <input id="identifier" name="identifier" type="text" value="{{ old('identifier', old('email')) }}" autocomplete="username" maxlength="254" required placeholder="matricula@exemplo.tjba.jus.br" @if($errors->has('identifier') || $errors->has('auth')) aria-invalid="true" @endif>
                                    @error('identifier') <p class="field-error">{{ $message }}</p> @enderror

                                    <label class="fld" for="password">Senha</label>
                                    <input id="password" name="password" type="password" autocomplete="current-password" required maxlength="1024">
                                    @error('password') <p class="field-error">{{ $message }}</p> @enderror

                                    <div class="rowl">
                                        <label class="remember" for="remember"><input id="remember" name="remember" type="checkbox" value="1"> Manter-me conectado</label>
                                        <a href="{{ route('account-password.request') }}">Esqueci a senha</a>
                                    </div>
                                    @error('auth') <p class="field-error">{{ $message }}</p> @enderror
                                    <button class="btn" type="submit">Entrar</button>
                                    <details class="mfa-extra">
                                        <summary>Segundo fator, se a conta tiver</summary>
                                        <label class="fld" for="totp">Código do autenticador</label>
                                        <input id="totp" name="totp" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="16">
                                        <label class="fld" for="recovery_code">Código de recuperação</label>
                                        <input id="recovery_code" name="recovery_code" type="text" autocomplete="one-time-code" maxlength="32">
                                    </details>
                                </form>
                            </div>

                            <div class="panel-cadastro" id="acesso-cadastro">
                                <div class="cadastro-note">
                                    <h3>Ainda não tem acesso?</h3>
                                    <p>Solicite cadastro. A liberação depende de aprovação da unidade.</p>
                                </div>
                                <form method="post" action="{{ route('account-requests.store') }}" data-lenis-prevent>
                                    @csrf
                                    <input type="hidden" name="form" value="cadastro">
                                    <input type="hidden" name="return" value="landing">
                                    <div class="two">
                                        <div>
                                            <label class="fld" for="display_name">Nome completo</label>
                                            <input id="display_name" name="display_name" type="text" value="{{ old('display_name') }}" autocomplete="name" maxlength="120" required placeholder="Seu nome" @if($cadastroAtivo) autofocus @endif>
                                        </div>
                                        <div>
                                            <label class="fld" for="matricula">Matrícula</label>
                                            <input id="matricula" name="matricula" type="text" value="{{ old('matricula') }}" inputmode="text" maxlength="32" placeholder="000000" autocomplete="off">
                                        </div>
                                    </div>
                                    @error('display_name') <p class="field-error">{{ $message }}</p> @enderror
                                    @error('matricula') <p class="field-error">{{ $message }}</p> @enderror
                                    <label class="fld" for="email">E-mail institucional</label>
                                    <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" maxlength="254" required placeholder="nome@exemplo.tjba.jus.br">
                                    @error('email') <p class="field-error">{{ $message }}</p> @enderror
                                    <button class="btn ghost" type="submit">Solicitar cadastro</button>
                                </form>
                            </div>
                        @else
                            <div class="panel-session">
                                <p class="kicker">Área de acesso</p>
                                <h2>Sessão iniciada</h2>
                                <p class="hint">Olá, {{ auth()->user()->name }}. O segundo fator é opcional. Os módulos continuam dependendo de concessão explícita.</p>
                                <form method="post" action="{{ route('logout') }}" data-lenis-prevent>
                                    @csrf
                                    <button class="btn" type="submit">Sair</button>
                                </form>
                            </div>
                        @endguest
                    </aside>
                    </div>
                </div>
                <a class="scroll-cue" href="#pauta"><i></i>Role para a pauta de hoje</a>
            </section>

            <section class="dash" id="produtividade">
                <img class="dash-media" src="{{ asset('images/hero-unit.jpg') }}" alt="">
                <div class="dash-shade" aria-hidden="true"></div>
                <div class="sechead">
                    <div>
                        <p class="eyebrow">Central da unidade · hoje, {{ $board['clock_label'] }}</p>
                        <h2>Hoje na unidade</h2>
                    </div>
                    <div class="status-row">
                        <span><i class="live"></i>Sistema operacional</span>
                        <span class="pill">Números de exemplo</span>
                    </div>
                </div>

                <section class="kpis" aria-label="Indicadores de exemplo">
                    @foreach ($board['kpis'] as $kpi)
                        <article class="kpi">
                            <div class="l">{{ $kpi['label'] }}</div>
                            <div class="n">{{ $kpi['value'] }} @if ($kpi['suffix'])<small>{{ $kpi['suffix'] }}</small>@endif</div>
                            <div class="s">{{ $kpi['detail'] }}</div>
                            <div class="bar"><i class="{{ $kpi['tone'] }}" style="width: {{ (int) $kpi['bar'] }}%"></i></div>
                        </article>
                    @endforeach
                </section>

                <div class="board" id="pauta">
                    <div class="sechead">
                        <div>
                            <h2>Audiências do dia</h2>
                            <p>Acompanhamento ao vivo · partes e números anonimizados (exemplo)</p>
                        </div>
                        <div class="legend">
                            <span><i style="background:#7dba8f"></i>Em andamento</span>
                            <span><i style="background:#7352a5"></i>Aguardando</span>
                            <span><i style="background:#c9a95e"></i>Confirmada</span>
                        </div>
                    </div>
                    <div class="table-scroll" data-lenis-prevent>
                        <table>
                            <thead>
                                <tr>
                                    <th>Horário</th>
                                    <th>Tipo</th>
                                    <th>Sala</th>
                                    <th>Processo</th>
                                    <th>Parte</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($board['hearings'] as $hearing)
                                    <tr @class(['on' => $hearing['current']])>
                                        <td class="time" data-label="Horário">{{ $hearing['time'] }}</td>
                                        <td data-label="Tipo">{{ $hearing['type'] }}</td>
                                        <td data-label="Sala">{{ $hearing['room'] }}</td>
                                        <td class="proc" data-label="Processo">{{ $hearing['process'] }}</td>
                                        <td data-label="Parte">{{ $hearing['party'] }}</td>
                                        <td data-label="Status"><span class="st {{ $hearing['tone'] }}">{{ $hearing['status'] }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="cards">
                    <article class="card">
                        <h3>Calendário de eventos · {{ $board['month_label'] }}</h3>
                        <div class="cal">
                            @foreach ($board['weekdays'] as $weekday)
                                <b>{{ $weekday }}</b>
                            @endforeach
                            @foreach ($board['calendar'] as $cell)
                                @if ($cell['day'] === null)
                                    <span></span>
                                @else
                                    <span @class(['hoje' => $cell['today']])>{{ $cell['day'] }}</span>
                                @endif
                            @endforeach
                        </div>
                        <ul class="ev">
                            @foreach ($board['events'] as $event)
                                <li><time>{{ $event['time'] }}</time><span>{{ $event['label'] }}</span></li>
                            @endforeach
                        </ul>
                    </article>
                    <article class="card" id="plantao">
                        <h3>Avisos e plantão</h3>
                        @foreach ($board['notices'] as $notice)
                            <div class="aviso"><b>{{ $notice['title'] }}</b>{{ $notice['body'] }}</div>
                        @endforeach
                        <div class="plantao"><b>{{ $board['duty']['title'] }}</b>{{ $board['duty']['body'] }}</div>
                    </article>
                    <article class="card unit-card">
                        <h3>Dados da unidade</h3>
                        <p><b>{{ $board['unit']['name'] }}</b><br>{{ $board['unit']['court'] }} · {{ $board['unit']['district'] }}</p>
                        <p class="muted">{{ $board['unit']['address_label'] }}: {{ $board['unit']['address'] }}<br>{{ $board['unit']['phone_label'] }}: {{ $board['unit']['phone'] }}<br>{{ $board['unit']['hours_label'] }}: {{ $board['unit']['hours'] }}</p>
                    </article>
                </div>
            </section>

            <footer class="foot">
                <span>Fokus Law on demand · 1ª Vara Crime de Ilhéus · TJBA</span>
                <span>Ambiente isolado · exemplo. Não insira dados pessoais, processos reais ou informações do PJe.</span>
            </footer>
        </div>
    </body>
</html>
