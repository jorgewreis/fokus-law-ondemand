<?php

namespace Tests\Feature;

use Tests\TestCase;

class InstitutionalContactsTest extends TestCase
{
    public function test_institutional_contacts_are_public_and_use_only_verified_official_values(): void
    {
        $this->get(route('institutional-contacts.show'))
            ->assertOk()
            ->assertSee('Contatos institucionais')
            ->assertSee('(11) 0000-0001')
            ->assertSee('contato@dominio-do-cliente.example')
            ->assertSee('Endereço institucional sintético do cliente on demand.')
            ->assertSee('Não informado na fonte oficial consultada; aguarda confirmação.')
            ->assertSee('https://example.com/contatos-institucionais')
            ->assertDontSee('Emanuele Vita Leite');
    }
}
