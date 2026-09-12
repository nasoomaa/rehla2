<?php

declare(strict_types=1);

namespace Rehla\Audit\Actions;

use Illuminate\Support\Facades\DB;
use Rehla\Audit\Contracts\AuditWriter;
use Rehla\Audit\Data\AppendAuditData;
use Rehla\Core\Identifiers\OpaqueId;
use Rehla\Core\Time\Clock;

final readonly class AppendAuditEntry implements AuditWriter
{
    /** @var list<string> */
    private const array SECRET_KEYS = [
        'password',
        'password_confirmation',
        'token',
        'access_token',
        'refresh_token',
        'authorization',
        'cookie',
        'mfa_secret',
        'secret',
        'document_content',
        'contents',
    ];

    public function __construct(private Clock $clock) {}

    public function append(AppendAuditData $data): string
    {
        $id = OpaqueId::generate()->value();

        DB::table('audit_entries')->insert([
            'id' => $id,
            'actor_type' => $data->actorType,
            'actor_id' => $data->actorId,
            'action' => $data->action,
            'subject_type' => $data->subjectType,
            'subject_id' => $data->subjectId,
            'metadata' => $this->json($this->sanitize($data->metadata)),
            'old_state' => $data->oldState === null ? null : $this->json($this->sanitize($data->oldState)),
            'new_state' => $data->newState === null ? null : $this->json($this->sanitize($data->newState)),
            'reason' => $data->reason,
            'correlation_id' => $data->correlationId,
            'ip_hash' => $data->ipHash,
            'user_agent_hash' => $data->userAgentHash,
            'occurred_at' => $this->clock->now(),
        ]);

        return $id;
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<array-key, mixed>
     */
    private function sanitize(array $values): array
    {
        $clean = [];

        foreach ($values as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::SECRET_KEYS, true)) {
                continue;
            }

            $clean[$key] = is_array($value) ? $this->sanitize($value) : $value;
        }

        return $clean;
    }

    /** @param array<array-key, mixed> $value */
    private function json(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR);
    }
}
