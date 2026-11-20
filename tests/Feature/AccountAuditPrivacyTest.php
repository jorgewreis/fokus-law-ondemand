<?php

namespace Tests\Feature;

use App\Models\AccountAuditEvent;
use App\Services\AccountAudit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class AccountAuditPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_persists_only_bounded_machine_readable_audit_metadata(): void
    {
        app(AccountAudit::class)->record(
            actor: null,
            event: 'person.deleted',
            subjectType: 'person',
            subjectId: 123,
            outcome: 'success',
            module: 'people',
            action: 'delete',
        );

        $event = AccountAuditEvent::query()->sole();

        $this->assertSame('person.deleted', $event->event);
        $this->assertSame('person', $event->subject_type);
        $this->assertSame('123', $event->subject_id);
        $this->assertSame('people', $event->module);
        $this->assertSame('delete', $event->action);
        $this->assertSame('success', $event->outcome);
        $this->assertSame([
            'id', 'actor_user_id', 'event', 'subject_type', 'subject_id',
            'module', 'action', 'outcome', 'created_at',
        ], array_keys($event->getAttributes()));
    }

    public function test_it_rejects_free_form_personal_data_in_metadata_fields(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(AccountAudit::class)->record(
            actor: null,
            event: 'Maria Silva foi excluída',
            subjectType: 'person',
            subjectId: 123,
        );
    }

    public function test_it_rejects_formatted_cnj_as_a_subject_identifier(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(AccountAudit::class)->record(
            actor: null,
            event: 'person.deleted',
            subjectType: 'person',
            subjectId: '0000000-00.2026.8.05.0001',
        );
    }

    public function test_it_rejects_unbounded_or_multiline_metadata(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(AccountAudit::class)->record(
            actor: null,
            event: "person.deleted\nCPF: 12345678901",
            subjectType: 'person',
            subjectId: 123,
        );
    }
}
