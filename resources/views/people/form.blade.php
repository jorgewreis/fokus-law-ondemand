@extends('layouts.auth')

@section('title', $person ? 'Editar Pessoa' : 'Cadastrar Pessoa')
@section('wide', 'true')

@section('content')
    <nav class="top-links" aria-label="Navegação de Pessoas">
        <a href="{{ route('people.index') }}">Consultar Pessoas</a>
        <a href="{{ route('home') }}">Início</a>
    </nav>
    <h1>{{ $person ? 'Editar Pessoa' : 'Cadastrar Pessoa' }}</h1>
    <p class="muted">Os dados informados são usados somente no cadastro sintético. Documentos são armazenados criptografados e não são comparados para deduplicação até que exista regra aprovada por tipo.</p>

    @php
        $documentTypes = [
            'RG' => ['Registro Geral', 'PF'], 'CPF' => ['CPF', 'PF'], 'CNPJ' => ['CNPJ', 'PJ'],
            'INSCRICAO_ESTADUAL' => ['Inscrição Estadual', 'PJ'], 'PASSAPORTE' => ['Passaporte', 'PF'],
            'CNH' => ['CNH', 'PF'], 'TITULO_ELEITOR' => ['Título de Eleitor', 'PF'], 'PIS' => ['PIS', 'PF'],
            'CAD' => ['CAD', 'PF/PJ'], 'MAT' => ['Matrícula Funcional', 'PF'], 'CIN' => ['CIN', 'PF'],
        ];
        $documentRows = old('documents', $person ? $person->documents->map(fn ($doc) => ['type' => $doc->type, 'number' => $doc->number, 'issuing_authority' => $doc->issuing_authority, 'issuing_state' => $doc->issuing_state])->all() : []);
        $phoneRows = old('phones', $person ? $person->phones->map(fn ($phone) => ['number' => app(\App\Services\BrazilianPhoneNumber::class)->format($phone->number), 'category' => $phone->category, 'is_primary' => $phone->is_primary])->all() : []);
        $whatsappRows = old('whatsapp', $person ? $person->whatsappIdentifiers->map(fn ($item) => ['type' => $item->type, 'value' => $item->value, 'user_key' => $item->user_key, 'is_primary' => $item->is_primary])->all() : []);
        $personType = $person?->person_type ?? old('person_type', '');
    @endphp

    <form method="post" action="{{ $person ? route('people.update', $person) : route('people.store') }}" class="stack">
        @csrf
        @if ($person)
            @method('PUT')
            <section class="panel"><p><strong>Classificação:</strong> {{ $person->person_type === 'PF' ? 'Pessoa Física' : 'Pessoa Jurídica' }} <span class="muted">(imutável após o cadastro)</span></p></section>
        @else
            <div class="field">
                <label for="person-type">Classificação</label>
                <select id="person-type" name="person_type" required>
                    <option value="">Selecione</option>
                    <option value="PF" @selected($personType === 'PF')>Pessoa Física</option>
                    <option value="PJ" @selected($personType === 'PJ')>Pessoa Jurídica</option>
                </select>
            </div>
        @endif

        <div class="field">
            <label for="name">Nome</label>
            <input id="name" name="name" maxlength="255" value="{{ old('name', $person?->name) }}" required autocomplete="off">
            <small>Uma possível correspondência de nome pode gerar um aviso genérico; comparação ignora maiúsculas/minúsculas e espaços repetidos, preservando acentos e pontuação. A confirmação permite cadastrar uma Pessoa distinta.</small>
        </div>

        <fieldset>
            <legend>Endereço</legend>
            <div class="field">
                <label for="address-postal-code">CEP</label>
                <div class="actions">
                    <input id="address-postal-code" name="address[postal_code]" inputmode="numeric" autocomplete="postal-code" maxlength="10" value="{{ old('address.postal_code', $person?->postal_code) }}" required>
                    <button type="button" class="secondary" id="lookup-cep">Consultar CEP</button>
                    <button type="button" class="secondary" id="manual-address" hidden>Preencher manualmente</button>
                </div>
                <small id="cep-result" role="status" aria-live="polite">A consulta envia somente o CEP ao ViaCEP.</small>
            </div>
            <div class="field"><label for="address-street">Logradouro</label><input id="address-street" name="address[street]" maxlength="255" autocomplete="address-line1" value="{{ old('address.street', $person?->street) }}" required></div>
            <div class="field"><label for="address-number">Número</label><input id="address-number" name="address[number]" maxlength="64" value="{{ old('address.number', $person?->address_number) }}" required></div>
            <div class="field"><label for="address-district">Bairro (opcional)</label><input id="address-district" name="address[district]" maxlength="160" value="{{ old('address.district', $person?->district) }}"></div>
            <div class="field"><label for="address-complement">Complemento (opcional)</label><input id="address-complement" name="address[complement]" maxlength="160" value="{{ old('address.complement', $person?->complement) }}"></div>
            <div class="field"><label for="address-city">Cidade</label><input id="address-city" name="address[city]" maxlength="160" value="{{ old('address.city', $person?->city) }}" required></div>
            <div class="field"><label for="address-state">UF</label><input id="address-state" name="address[state]" minlength="2" maxlength="2" autocomplete="address-level1" value="{{ old('address.state', $person?->state) }}" required></div>
        </fieldset>

        <fieldset>
            <legend>Telefones (opcionais)</legend>
            <p class="muted">Quando houver telefones, marque exatamente um como principal. Armazenamento em dígitos nacionais e exibição brasileira.</p>
            <div id="phone-list">
                @foreach ($phoneRows as $index => $phone)
                    <div class="panel phone-row">
                        <div class="field"><label for="phone-number-{{ $index }}">Telefone</label><input id="phone-number-{{ $index }}" name="phones[{{ $index }}][number]" inputmode="tel" maxlength="40" value="{{ $phone['number'] ?? '' }}" required></div>
                        <div class="field"><label for="phone-category-{{ $index }}">Categoria</label><select id="phone-category-{{ $index }}" name="phones[{{ $index }}][category]" required><option value="">Selecione</option><option value="Celular" @selected(($phone['category'] ?? '') === 'Celular')>Celular</option><option value="Fixo" @selected(($phone['category'] ?? '') === 'Fixo')>Fixo</option></select></div>
                        <label class="check"><input type="checkbox" name="phones[{{ $index }}][is_primary]" value="1" @checked(filter_var($phone['is_primary'] ?? false, FILTER_VALIDATE_BOOLEAN))><span>Principal</span></label>
                        <button type="button" class="secondary remove-row">Remover telefone</button>
                    </div>
                @endforeach
            </div>
            <button type="button" class="secondary" data-add-row="phone">Adicionar telefone</button>
        </fieldset>

        <fieldset>
            <legend>Documentos opcionais</legend>
            <p class="muted">Apenas tipos aplicáveis à classificação serão aceitos. CPF/CNPJ têm validação local. O valor original é preservado; a comparação de duplicidade documental permanece desabilitada até aprovação da regra por tipo.</p>
            <div id="document-list">
                @foreach ($documentRows as $index => $document)
                    <div class="panel document-row">
                        <div class="field"><label for="document-type-{{ $index }}">Tipo</label><select id="document-type-{{ $index }}" class="document-type" name="documents[{{ $index }}][type]"><option value="">Selecione</option>@foreach ($documentTypes as $code => [$label, $applies])<option value="{{ $code }}" data-applies="{{ $applies }}" @selected(($document['type'] ?? '') === $code)>{{ $label }}</option>@endforeach</select></div>
                        <div class="field"><label for="document-number-{{ $index }}">Número</label><input id="document-number-{{ $index }}" name="documents[{{ $index }}][number]" maxlength="255" value="{{ $document['number'] ?? '' }}"></div>
                        <div class="field"><label for="document-authority-{{ $index }}">Órgão emissor (opcional)</label><input id="document-authority-{{ $index }}" name="documents[{{ $index }}][issuing_authority]" maxlength="120" value="{{ $document['issuing_authority'] ?? '' }}"></div>
                        <div class="field"><label for="document-state-{{ $index }}">UF emissora (opcional)</label><input id="document-state-{{ $index }}" name="documents[{{ $index }}][issuing_state]" maxlength="2" value="{{ $document['issuing_state'] ?? '' }}"></div>
                        <button type="button" class="secondary remove-row">Remover documento</button>
                    </div>
                @endforeach
            </div>
            <button type="button" class="secondary" data-add-row="document">Adicionar documento</button>
        </fieldset>

        <fieldset>
            <legend>Identificadores WhatsApp (opcionais)</legend>
            <p class="muted">Informações de identificação, sem envio ou integração de mensagens. Quando houver identificadores, marque exatamente um como principal.</p>
            <div id="whatsapp-list">
                @foreach ($whatsappRows as $index => $item)
                    <div class="panel whatsapp-row">
                        <div class="field"><label for="whatsapp-type-{{ $index }}">Tipo</label><select id="whatsapp-type-{{ $index }}" class="whatsapp-type" name="whatsapp[{{ $index }}][type]" required><option value="">Selecione</option><option value="phone" @selected(($item['type'] ?? '') === 'phone')>Telefone</option><option value="username" @selected(($item['type'] ?? '') === 'username')>Nome de usuário</option></select></div>
                        <div class="field"><label for="whatsapp-value-{{ $index }}">Identificador</label><input id="whatsapp-value-{{ $index }}" name="whatsapp[{{ $index }}][value]" maxlength="255" value="{{ $item['value'] ?? '' }}" required></div>
                        <div class="field"><label for="whatsapp-key-{{ $index }}">Chave do usuário (opcional)</label><input id="whatsapp-key-{{ $index }}" name="whatsapp[{{ $index }}][user_key]" maxlength="255" value="{{ $item['user_key'] ?? '' }}"></div>
                        <label class="check"><input type="checkbox" name="whatsapp[{{ $index }}][is_primary]" value="1" @checked(filter_var($item['is_primary'] ?? false, FILTER_VALIDATE_BOOLEAN))><span>Principal</span></label>
                        <button type="button" class="secondary remove-row">Remover identificador</button>
                    </div>
                @endforeach
            </div>
            <button type="button" class="secondary" data-add-row="whatsapp">Adicionar identificador</button>
        </fieldset>

        @if (! $person)
            <label class="check"><input type="checkbox" name="confirm_duplicate" value="1"><span>Confirmo que devo cadastrar esta Pessoa distinta, mesmo que o nome informado corresponda a outro cadastro acessível.</span></label>
        @else
            <label class="check"><input type="checkbox" name="confirm_duplicate" value="1"><span>Confirmo que devo manter esta Pessoa distinta, mesmo que o nome informado corresponda a outro cadastro acessível.</span></label>
        @endif
        <p class="muted">Os valores dos formulários não são guardados como entrada antiga na sessão após erro de validação; será necessário digitá-los novamente.</p>
        <div class="actions"><button type="submit">{{ $person ? 'Salvar alterações' : 'Cadastrar Pessoa' }}</button><a class="button secondary" href="{{ $person ? route('people.show', $person) : route('people.index') }}">Cancelar</a></div>
    </form>

    <template id="phone-template"><div class="panel phone-row"><div class="field"><label>Telefone</label><input data-name="number" inputmode="tel" maxlength="40" required></div><div class="field"><label>Categoria</label><select data-name="category" required><option value="">Selecione</option><option value="Celular">Celular</option><option value="Fixo">Fixo</option></select></div><label class="check"><input data-name="is_primary" type="checkbox" value="1"><span>Principal</span></label><button type="button" class="secondary remove-row">Remover telefone</button></div></template>
    <template id="document-template"><div class="panel document-row"><div class="field"><label>Tipo</label><select data-name="type" class="document-type"><option value="">Selecione</option>@foreach ($documentTypes as $code => [$label, $applies])<option value="{{ $code }}" data-applies="{{ $applies }}">{{ $label }}</option>@endforeach</select></div><div class="field"><label>Número</label><input data-name="number" maxlength="255"></div><div class="field"><label>Órgão emissor (opcional)</label><input data-name="issuing_authority" maxlength="120"></div><div class="field"><label>UF emissora (opcional)</label><input data-name="issuing_state" maxlength="2"></div><button type="button" class="secondary remove-row">Remover documento</button></div></template>
    <template id="whatsapp-template"><div class="panel whatsapp-row"><div class="field"><label>Tipo</label><select data-name="type" class="whatsapp-type" required><option value="">Selecione</option><option value="phone">Telefone</option><option value="username">Nome de usuário</option></select></div><div class="field"><label>Identificador</label><input data-name="value" maxlength="255" required></div><div class="field"><label>Chave do usuário (opcional)</label><input data-name="user_key" maxlength="255"></div><label class="check"><input data-name="is_primary" type="checkbox" value="1"><span>Principal</span></label><button type="button" class="secondary remove-row">Remover identificador</button></div></template>

    <script>
        (() => {
            const typeSelect = document.getElementById('person-type');
            const documents = document.getElementById('document-list');
            let index = {phone: {{ count($phoneRows) }}, document: {{ count($documentRows) }}, whatsapp: {{ count($whatsappRows) }}};
            const allowed = {PF: ['PF', 'PF/PJ'], PJ: ['PJ', 'PF/PJ']};
            const updateDocumentChoices = () => {
                const type = typeSelect?.value || @json($personType);
                documents?.querySelectorAll('select.document-type').forEach((select) => {
                    Array.from(select.options).forEach((option) => {
                        if (option.value) option.hidden = !allowed[type]?.includes(option.dataset.applies);
                    });
                    if (select.selectedOptions[0]?.hidden) select.value = '';
                });
            };
            typeSelect?.addEventListener('change', updateDocumentChoices);
            updateDocumentChoices();

            document.querySelectorAll('[data-add-row]').forEach((button) => button.addEventListener('click', () => {
                const kind = button.dataset.addRow;
                const template = document.getElementById(`${kind}-template`);
                const clone = template.content.cloneNode(true);
                clone.querySelectorAll('[data-name]').forEach((field) => {
                    const name = field.dataset.name;
                    field.name = kind === 'document' ? `documents[${index[kind]}][${name}]` : kind === 'whatsapp' ? `whatsapp[${index[kind]}][${name}]` : `phones[${index[kind]}][${name}]`;
                });
                index[kind]++;
                document.getElementById(`${kind}-list`).appendChild(clone);
                updateDocumentChoices();
            }));
            document.addEventListener('click', (event) => {
                if (event.target.matches('.remove-row')) event.target.closest('.panel').remove();
            });

            const lookup = document.getElementById('lookup-cep');
            const manual = document.getElementById('manual-address');
            const cep = document.getElementById('address-postal-code');
            const result = document.getElementById('cep-result');
            const fields = {street: document.getElementById('address-street'), district: document.getElementById('address-district'), city: document.getElementById('address-city'), state: document.getElementById('address-state')};
            lookup?.addEventListener('click', async () => {
                lookup.disabled = true;
                result.textContent = 'Consultando CEP…';
                try {
                    const response = await fetch(@json(route('people.address.lookup')), {method: 'POST', headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token())}, body: JSON.stringify({cep: cep.value})});
                    const data = await response.json();
                    if (!response.ok || !data.found) throw new Error(data.message || 'Não foi possível localizar o CEP. Preencha o endereço manualmente.');
                    Object.entries(fields).forEach(([key, field]) => { field.value = data.address[key] || ''; field.readOnly = true; });
                    result.textContent = 'Endereço localizado. Os campos retornados estão bloqueados; número e complemento continuam editáveis.';
                    manual.hidden = false;
                } catch (error) {
                    Object.values(fields).forEach((field) => { field.readOnly = false; });
                    result.textContent = error.message || 'Falha na consulta. Preencha o endereço manualmente.';
                    manual.hidden = false;
                } finally { lookup.disabled = false; }
            });
            manual?.addEventListener('click', () => {
                Object.values(fields).forEach((field) => { field.readOnly = false; });
                result.textContent = 'Preencha o endereço manualmente.';
                manual.hidden = true;
            });
        })();
    </script>
@endsection
