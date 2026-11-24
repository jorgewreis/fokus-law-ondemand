@extends('layouts.auth')

@section('title', 'Meus contatos')
@section('content')
    @php
        $phoneRows = old('phones', $person->phones->map(fn ($phone) => [
            'id' => $phone->getKey(),
            'number' => app(\App\Services\BrazilianPhoneNumber::class)->format($phone->number),
            'category' => $phone->category,
            'is_primary' => $phone->is_primary,
        ])->all());
    @endphp
    <nav class="top-links" aria-label="Navegação de contato próprio">
        <a href="{{ route('home') }}">Início</a>
    </nav>
    <h1>Meus telefones e endereço</h1>
    <p class="muted">Esta tela altera somente endereço e telefones do cadastro Pessoa vinculado à sua conta. Outros dados permanecem protegidos.</p>
    <form method="post" action="{{ route('people.own-contacts.update') }}" class="stack">
        @csrf @method('put')
        <fieldset>
            <legend>Endereço</legend>
            <div class="field">
                <label for="address-postal-code">CEP</label>
                <div class="actions">
                    <input id="address-postal-code" name="address[postal_code]" inputmode="numeric" autocomplete="postal-code" maxlength="10" value="{{ old('address.postal_code', $person->postal_code) }}" required>
                    <button class="secondary" type="button" id="lookup-cep">Consultar CEP</button>
                    <button class="secondary" type="button" id="manual-address" hidden>Preencher manualmente</button>
                </div>
                <small id="cep-result" role="status" aria-live="polite">A consulta envia somente o CEP.</small>
            </div>
            <div class="field"><label for="address-street">Logradouro</label><input id="address-street" name="address[street]" maxlength="255" value="{{ old('address.street', $person->street) }}" required></div>
            <div class="field"><label for="address-number">Número</label><input id="address-number" name="address[number]" maxlength="64" value="{{ old('address.number', $person->address_number) }}" required></div>
            <div class="field"><label for="address-district">Bairro (opcional)</label><input id="address-district" name="address[district]" maxlength="160" value="{{ old('address.district', $person->district) }}"></div>
            <div class="field"><label for="address-complement">Complemento (opcional)</label><input id="address-complement" name="address[complement]" maxlength="160" value="{{ old('address.complement', $person->complement) }}"></div>
            <div class="field"><label for="address-city">Cidade</label><input id="address-city" name="address[city]" maxlength="160" value="{{ old('address.city', $person->city) }}" required></div>
            <div class="field"><label for="address-state">UF</label><input id="address-state" name="address[state]" minlength="2" maxlength="2" value="{{ old('address.state', $person->state) }}" required></div>
        </fieldset>
        <fieldset>
            <legend>Telefones</legend>
            <p class="muted">Quando houver números, exatamente um deve ser o principal. Um telefone associado a um identificador WhatsApp não pode ser alterado ou removido nesta tela.</p>
            <div id="phone-list">
                @foreach ($phoneRows as $index => $phone)
                    <article class="panel phone-row">
                        @if (!empty($phone['id']))<input type="hidden" name="phones[{{ $index }}][id]" value="{{ $phone['id'] }}">@endif
                        <div class="field"><label for="phone-number-{{ $index }}">Telefone</label><input id="phone-number-{{ $index }}" name="phones[{{ $index }}][number]" inputmode="tel" maxlength="40" value="{{ $phone['number'] ?? '' }}" required></div>
                        <div class="field"><label for="phone-category-{{ $index }}">Categoria</label><select id="phone-category-{{ $index }}" name="phones[{{ $index }}][category]" required><option value="">Selecione</option><option value="Celular" @selected(($phone['category'] ?? '') === 'Celular')>Celular</option><option value="Fixo" @selected(($phone['category'] ?? '') === 'Fixo')>Fixo</option></select></div>
                        <label class="check"><input type="checkbox" name="phones[{{ $index }}][is_primary]" value="1" @checked(filter_var($phone['is_primary'] ?? false, FILTER_VALIDATE_BOOLEAN))><span>Principal</span></label>
                        <button class="secondary remove-row" type="button">Remover telefone</button>
                    </article>
                @endforeach
            </div>
            <button class="secondary" type="button" id="add-phone">Adicionar telefone</button>
        </fieldset>
        <button type="submit">Salvar contatos</button>
    </form>
    <template id="phone-template">
        <article class="panel phone-row">
            <div class="field"><label for="phone-number-__INDEX__">Telefone</label><input id="phone-number-__INDEX__" name="phones[__INDEX__][number]" inputmode="tel" maxlength="40" required></div>
            <div class="field"><label for="phone-category-__INDEX__">Categoria</label><select id="phone-category-__INDEX__" name="phones[__INDEX__][category]" required><option value="">Selecione</option><option value="Celular">Celular</option><option value="Fixo">Fixo</option></select></div>
            <label class="check"><input type="checkbox" name="phones[__INDEX__][is_primary]" value="1"><span>Principal</span></label>
            <button class="secondary remove-row" type="button">Remover telefone</button>
        </article>
    </template>
    <script>
        (() => {
            let next = {{ count($phoneRows) ? max(array_map('intval', array_keys($phoneRows))) + 1 : 0 }};
            document.getElementById('add-phone').addEventListener('click', () => {
                const html = document.getElementById('phone-template').innerHTML.replaceAll('__INDEX__', String(next++));
                document.getElementById('phone-list').insertAdjacentHTML('beforeend', html);
            });
            document.addEventListener('click', (event) => {
                if (event.target.matches('.remove-row')) event.target.closest('.phone-row').remove();
            });
            const lookup = document.getElementById('lookup-cep');
            const manual = document.getElementById('manual-address');
            const cep = document.getElementById('address-postal-code');
            const result = document.getElementById('cep-result');
            const fields = {street: document.getElementById('address-street'), district: document.getElementById('address-district'), city: document.getElementById('address-city'), state: document.getElementById('address-state')};
            lookup.addEventListener('click', async () => {
                lookup.disabled = true;
                result.textContent = 'Consultando CEP…';
                try {
                    const response = await fetch(@json(route('people.own-contacts.address.lookup')), {method: 'POST', headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token())}, body: JSON.stringify({cep: cep.value})});
                    const data = await response.json();
                    if (!response.ok || !data.found) throw new Error(data.message || 'Não foi possível localizar o CEP. Preencha manualmente.');
                    Object.entries(fields).forEach(([key, field]) => { field.value = data.address[key] || ''; field.readOnly = true; });
                    result.textContent = 'Endereço localizado. Os campos retornados estão bloqueados; número e complemento continuam editáveis.';
                    manual.hidden = false;
                } catch (error) {
                    Object.values(fields).forEach((field) => { field.readOnly = false; });
                    result.textContent = error.message || 'Falha na consulta. Preencha manualmente.';
                    manual.hidden = false;
                } finally { lookup.disabled = false; }
            });
            manual.addEventListener('click', () => {
                Object.values(fields).forEach((field) => { field.readOnly = false; });
                result.textContent = 'Preencha o endereço manualmente.';
                manual.hidden = true;
            });
        })();
    </script>
@endsection
