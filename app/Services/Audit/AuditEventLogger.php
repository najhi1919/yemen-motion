<?php

namespace App\Services\Audit;

use App\Models\AuditEvent;
use DateTimeInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class AuditEventLogger
{
    private const REDACTED_VALUE = '[REDACTED]';

    private const MAX_METADATA_DEPTH = 5;

    private const MAX_METADATA_ITEMS = 100;

    private const MAX_METADATA_KEY_LENGTH = 100;

    private const MAX_METADATA_STRING_LENGTH = 1000;

    /**
     * قائمة الحقول المباشرة المعتمدة تمنع تمرير payload أو أسرار عرضية إلى النموذج.
     *
     * @var list<string>
     */
    private const RECORDABLE_FIELDS = [
        'event_type',
        'category',
        'severity',
        'actor_type',
        'actor_id',
        'actor_role',
        'target_type',
        'target_id',
        'action',
        'outcome',
        'ip_address',
        'user_agent',
        'request_id',
        'correlation_id',
        'metadata',
        'occurred_at',
    ];

    /**
     * أطوال الحقول النصية المباشرة قبل الحفظ.
     *
     * @var array<string, int>
     */
    private const DIRECT_STRING_LIMITS = [
        'event_type' => 255,
        'category' => 255,
        'severity' => 255,
        'actor_type' => 255,
        'actor_role' => 255,
        'target_type' => 255,
        'action' => 255,
        'outcome' => 255,
        'ip_address' => 45,
        'user_agent' => 2000,
        'request_id' => 255,
        'correlation_id' => 255,
    ];

    /**
     * أجزاء أسماء المفاتيح التي تدل على بيانات حساسة مهما كان عمقها.
     *
     * @var list<string>
     */
    private const SENSITIVE_KEY_FRAGMENTS = [
        'password',
        'token',
        'cookie',
        'secret',
        'api_key',
        'apikey',
        'authorization',
        'private_key',
        'session_id',
        'card_number',
        'card_data',
        'card_cvv',
        'payload',
    ];

    /**
     * أسماء حساسة لا تحتاج مطابقة جزئية.
     *
     * @var list<string>
     */
    private const SENSITIVE_KEYS = [
        'session',
        'cvv',
        'cvc',
        'pin',
        'body',
        'raw_body',
        'request_body',
        'response_body',
        'file_contents',
    ];

    /**
     * يسجل حدثًا منظمًا بعد إسقاط الحقول غير المعتمدة وتنقيح metadata.
     *
     * @param array<string, mixed> $event
     */
    public function record(array $event): AuditEvent
    {
        // لا نمرر إلا الحقول المعتمدة، لذلك تسقط كلمات المرور والتوكنات وpayloads المباشرة تلقائيًا.
        $attributes = Arr::only($event, self::RECORDABLE_FIELDS);
        $attributes = $this->sanitizeDirectFields($attributes);

        $attributes['outcome'] = $attributes['outcome'] ?? 'success';
        $attributes['occurred_at'] = $event['occurred_at'] ?? now();

        if (array_key_exists('metadata', $event)) {
            $attributes['metadata'] = $this->sanitizeMetadata($event['metadata']);
        }

        return AuditEvent::query()->create($attributes);
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    private function sanitizeDirectFields(array $attributes): array
    {
        foreach (self::DIRECT_STRING_LIMITS as $field => $limit) {
            if (!array_key_exists($field, $attributes) || $attributes[$field] === null) {
                continue;
            }

            if (!is_scalar($attributes[$field])) {
                unset($attributes[$field]);

                continue;
            }

            $attributes[$field] = Str::limit(trim((string) $attributes[$field]), $limit, '');
        }

        foreach (['actor_id', 'target_id'] as $field) {
            if (!array_key_exists($field, $attributes) || $attributes[$field] === null) {
                continue;
            }

            $normalizedId = filter_var(
                $attributes[$field],
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]],
            );

            if ($normalizedId === false) {
                unset($attributes[$field]);

                continue;
            }

            $attributes[$field] = $normalizedId;
        }

        unset($attributes['metadata']);

        return $attributes;
    }

    /**
     * metadata تقبل المصفوفات فقط، وأي قيمة أخرى تهمل بدل تحويل payload عشوائي إلى نص.
     *
     * @return array<array-key, mixed>|null
     */
    private function sanitizeMetadata(mixed $metadata): ?array
    {
        if (!is_array($metadata)) {
            return null;
        }

        $processedItems = 0;

        return $this->sanitizeMetadataArray($metadata, 0, $processedItems);
    }

    /**
     * التنقيح متكرر ويحمي كل المستويات مع حد موحد للعمق وعدد العناصر.
     *
     * @param array<array-key, mixed> $metadata
     * @return array<array-key, mixed>
     */
    private function sanitizeMetadataArray(array $metadata, int $depth, int &$processedItems): array
    {
        if ($depth >= self::MAX_METADATA_DEPTH) {
            return [];
        }

        $sanitized = [];

        foreach ($metadata as $key => $value) {
            if ($processedItems >= self::MAX_METADATA_ITEMS) {
                break;
            }

            $processedItems++;
            $safeKey = is_int($key)
                ? $key
                : Str::limit((string) $key, self::MAX_METADATA_KEY_LENGTH, '');

            // نستبدل القيمة الحساسة بعلامة واضحة ولا نفحص محتواها أو نخزن أي جزء منها.
            if (is_string($key) && $this->isSensitiveKey($key)) {
                $sanitized[$safeKey] = self::REDACTED_VALUE;

                continue;
            }

            $sanitized[$safeKey] = $this->sanitizeMetadataValue(
                $value,
                $depth + 1,
                $processedItems,
            );
        }

        return $sanitized;
    }

    private function sanitizeMetadataValue(mixed $value, int $depth, int &$processedItems): mixed
    {
        if (is_array($value)) {
            return $this->sanitizeMetadataArray($value, $depth, $processedItems);
        }

        if (is_string($value)) {
            return Str::limit($value, self::MAX_METADATA_STRING_LENGTH, '');
        }

        if (is_int($value) || is_bool($value) || $value === null) {
            return $value;
        }

        if (is_float($value)) {
            return is_finite($value) ? $value : null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }

        // لا نحول Models أو Requests أو كائنات غير معروفة إلى payload ضمني.
        return null;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = Str::of($key)
            ->snake()
            ->lower()
            ->replaceMatches('/[^a-z0-9_]+/', '_')
            ->trim('_')
            ->toString();
        $collapsed = str_replace('_', '', $normalized);

        if (in_array($normalized, self::SENSITIVE_KEYS, true)) {
            return true;
        }

        foreach (self::SENSITIVE_KEY_FRAGMENTS as $fragment) {
            if (
                str_contains($normalized, $fragment)
                || str_contains($collapsed, str_replace('_', '', $fragment))
            ) {
                return true;
            }
        }

        return false;
    }
}
