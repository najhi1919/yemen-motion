<?php

declare(strict_types=1);

namespace App\Services\Works;

use App\Models\WorkSetting;

class WorksSettingsStore
{
    /**
     * @var list<string>
     */
    private const KNOWN_KEYS = [
        'review_sla_hours',
        'direct_publish_trust_enabled',
        'media_limits',
    ];

    /**
     * @var list<string>
     */
    private const KNOWN_MEDIA_LIMIT_KEYS = [
        'max_items',
        'max_file_size_kb',
        'allowed_types',
    ];

    /**
     * @var list<string>
     */
    private const ALLOWED_MEDIA_TYPES = [
        'image',
        'video',
        'gallery',
    ];

    /**
     * @return array{
     *     scope: string,
     *     version: int,
     *     values: array{
     *         review_sla_hours: int|null,
     *         direct_publish_trust_enabled: bool,
     *         media_limits: array{
     *             max_items: int|null,
     *             max_file_size_kb: int|null,
     *             allowed_types: list<string>|null
     *         }
     *     },
     *     storage_record_found: bool,
     *     updated_at: string|null
     * }
     */
    public function getGlobalSettings(): array
    {
        $setting = WorkSetting::query()
            ->where('scope', WorkSetting::SCOPE_GLOBAL)
            ->first();

        if ($setting === null) {
            return [
                'scope' => WorkSetting::SCOPE_GLOBAL,
                'version' => 1,
                'values' => $this->defaultValues(),
                'storage_record_found' => false,
                'updated_at' => null,
            ];
        }

        $storedValues = is_array($setting->values) ? $setting->values : [];

        return [
            'scope' => WorkSetting::SCOPE_GLOBAL,
            'version' => (int) $setting->version,
            'values' => $this->normalizeValues($storedValues),
            'storage_record_found' => true,
            'updated_at' => $setting->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{
     *     review_sla_hours: null,
     *     direct_publish_trust_enabled: false,
     *     media_limits: array{
     *         max_items: null,
     *         max_file_size_kb: null,
     *         allowed_types: null
     *     }
     * }
     */
    private function defaultValues(): array
    {
        return [
            'review_sla_hours' => null,
            'direct_publish_trust_enabled' => false,
            'media_limits' => [
                'max_items' => null,
                'max_file_size_kb' => null,
                'allowed_types' => null,
            ],
        ];
    }

    /**
     * @param array<array-key, mixed> $values
     * @return array{
     *     review_sla_hours: int|null,
     *     direct_publish_trust_enabled: bool,
     *     media_limits: array{
     *         max_items: int|null,
     *         max_file_size_kb: int|null,
     *         allowed_types: list<string>|null
     *     }
     * }
     */
    private function normalizeValues(array $values): array
    {
        $defaults = $this->defaultValues();
        $values = array_intersect_key($values, array_flip(self::KNOWN_KEYS));
        $reviewSlaHours = $values['review_sla_hours'] ?? null;
        $directPublishTrustEnabled = $values['direct_publish_trust_enabled'] ?? null;
        $mediaLimits = $values['media_limits'] ?? null;

        return [
            'review_sla_hours' => is_int($reviewSlaHours)
                && $reviewSlaHours >= 1
                && $reviewSlaHours <= 720
                    ? $reviewSlaHours
                    : $defaults['review_sla_hours'],
            'direct_publish_trust_enabled' => is_bool($directPublishTrustEnabled)
                ? $directPublishTrustEnabled
                : $defaults['direct_publish_trust_enabled'],
            'media_limits' => is_array($mediaLimits)
                ? $this->normalizeMediaLimits($mediaLimits)
                : $defaults['media_limits'],
        ];
    }

    /**
     * @param array<array-key, mixed> $mediaLimits
     * @return array{
     *     max_items: int|null,
     *     max_file_size_kb: int|null,
     *     allowed_types: list<string>|null
     * }
     */
    private function normalizeMediaLimits(array $mediaLimits): array
    {
        $mediaLimits = array_intersect_key(
            $mediaLimits,
            array_flip(self::KNOWN_MEDIA_LIMIT_KEYS),
        );
        $maxItems = $mediaLimits['max_items'] ?? null;
        $maxFileSize = $mediaLimits['max_file_size_kb'] ?? null;
        $allowedTypes = $mediaLimits['allowed_types'] ?? null;

        $normalizedAllowedTypes = null;

        if (is_array($allowedTypes)) {
            $knownTypes = array_values(array_unique(array_filter(
                $allowedTypes,
                static fn (mixed $type): bool => is_string($type)
                    && in_array($type, self::ALLOWED_MEDIA_TYPES, true),
            )));

            $normalizedAllowedTypes = $knownTypes === [] ? null : $knownTypes;
        }

        return [
            'max_items' => is_int($maxItems) && $maxItems >= 1 && $maxItems <= 100
                ? $maxItems
                : null,
            'max_file_size_kb' => is_int($maxFileSize)
                && $maxFileSize >= 1
                && $maxFileSize <= 2097152
                    ? $maxFileSize
                    : null,
            'allowed_types' => $normalizedAllowedTypes,
        ];
    }
}
