<?php

declare(strict_types=1);

namespace App\Services\Works;

use App\Exceptions\WorksSettingsVersionConflictException;
use App\Models\WorkSetting;
use Illuminate\Support\Facades\DB;

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
     * @param array<array-key, mixed> $requestedValues
     * @param callable(WorkSetting, array{
     *     changed: true,
     *     changed_keys: list<string>,
     *     previous_version: int,
     *     current_version: int
     * }): void $afterSave
     * @return array{
     *     changed: bool,
     *     changed_keys: list<string>,
     *     previous_version: int,
     *     current_version: int,
     *     stored_settings: array{
     *         scope: string,
     *         version: int,
     *         values: array{
     *             review_sla_hours: int|null,
     *             direct_publish_trust_enabled: bool,
     *             media_limits: array{
     *                 max_items: int|null,
     *                 max_file_size_kb: int|null,
     *                 allowed_types: list<string>|null
     *             }
     *         },
     *         storage_record_found: bool,
     *         updated_at: string|null
     *     }
     * }
     *
     * @throws WorksSettingsVersionConflictException
     */
    public function updateGlobalSettings(
        int $expectedVersion,
        array $requestedValues,
        int $updatedBy,
        callable $afterSave,
    ): array {
        return DB::transaction(function () use (
            $expectedVersion,
            $requestedValues,
            $updatedBy,
            $afterSave,
        ): array {
            $setting = WorkSetting::query()
                ->where('scope', WorkSetting::SCOPE_GLOBAL)
                ->lockForUpdate()
                ->first();

            $previousVersion = $setting === null ? 1 : (int) $setting->version;

            if ($expectedVersion !== $previousVersion) {
                throw new WorksSettingsVersionConflictException($previousVersion);
            }

            $storedValues = $setting !== null && is_array($setting->values)
                ? $setting->values
                : [];
            $currentValues = $this->normalizeValues($storedValues);
            $mergedValues = $this->mergeValues($currentValues, $requestedValues);
            $changedKeys = $this->changedKeys(
                $currentValues,
                $mergedValues,
                $requestedValues,
            );

            if ($changedKeys === []) {
                return [
                    'changed' => false,
                    'changed_keys' => [],
                    'previous_version' => $previousVersion,
                    'current_version' => $previousVersion,
                    'stored_settings' => $setting === null
                        ? [
                            'scope' => WorkSetting::SCOPE_GLOBAL,
                            'version' => 1,
                            'values' => $currentValues,
                            'storage_record_found' => false,
                            'updated_at' => null,
                        ]
                        : $this->storedSettingsContract($setting, $currentValues),
                ];
            }

            $currentVersion = $previousVersion + 1;

            if ($setting === null) {
                $setting = WorkSetting::query()->create([
                    'scope' => WorkSetting::SCOPE_GLOBAL,
                    'values' => $mergedValues,
                    'version' => $currentVersion,
                    'updated_by' => $updatedBy,
                ]);
            } else {
                $setting->forceFill([
                    'values' => $mergedValues,
                    'version' => $currentVersion,
                    'updated_by' => $updatedBy,
                ])->save();
            }

            $auditContext = [
                'changed' => true,
                'changed_keys' => $changedKeys,
                'previous_version' => $previousVersion,
                'current_version' => $currentVersion,
            ];

            $afterSave($setting, $auditContext);

            return [
                ...$auditContext,
                'stored_settings' => $this->storedSettingsContract($setting, $mergedValues),
            ];
        });
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

    /**
     * @param array{
     *     review_sla_hours: int|null,
     *     direct_publish_trust_enabled: bool,
     *     media_limits: array{
     *         max_items: int|null,
     *         max_file_size_kb: int|null,
     *         allowed_types: list<string>|null
     *     }
     * } $currentValues
     * @param array<array-key, mixed> $requestedValues
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
    private function mergeValues(array $currentValues, array $requestedValues): array
    {
        $mergedValues = $currentValues;
        $requestedValues = array_intersect_key(
            $requestedValues,
            array_flip(self::KNOWN_KEYS),
        );

        foreach (['review_sla_hours', 'direct_publish_trust_enabled'] as $key) {
            if (array_key_exists($key, $requestedValues)) {
                $mergedValues[$key] = $requestedValues[$key];
            }
        }

        if (is_array($requestedValues['media_limits'] ?? null)) {
            $requestedMediaLimits = array_intersect_key(
                $requestedValues['media_limits'],
                array_flip(self::KNOWN_MEDIA_LIMIT_KEYS),
            );

            foreach (self::KNOWN_MEDIA_LIMIT_KEYS as $key) {
                if (array_key_exists($key, $requestedMediaLimits)) {
                    $mergedValues['media_limits'][$key] = $requestedMediaLimits[$key];
                }
            }
        }

        return $this->normalizeValues($mergedValues);
    }

    /**
     * @param array{
     *     review_sla_hours: int|null,
     *     direct_publish_trust_enabled: bool,
     *     media_limits: array{
     *         max_items: int|null,
     *         max_file_size_kb: int|null,
     *         allowed_types: list<string>|null
     *     }
     * } $currentValues
     * @param array{
     *     review_sla_hours: int|null,
     *     direct_publish_trust_enabled: bool,
     *     media_limits: array{
     *         max_items: int|null,
     *         max_file_size_kb: int|null,
     *         allowed_types: list<string>|null
     *     }
     * } $mergedValues
     * @param array<array-key, mixed> $requestedValues
     * @return list<string>
     */
    private function changedKeys(
        array $currentValues,
        array $mergedValues,
        array $requestedValues,
    ): array {
        $changedKeys = [];

        foreach (['review_sla_hours', 'direct_publish_trust_enabled'] as $key) {
            if (array_key_exists($key, $requestedValues)
                && $currentValues[$key] !== $mergedValues[$key]) {
                $changedKeys[] = $key;
            }
        }

        $requestedMediaLimits = is_array($requestedValues['media_limits'] ?? null)
            ? $requestedValues['media_limits']
            : [];

        foreach (self::KNOWN_MEDIA_LIMIT_KEYS as $key) {
            if (array_key_exists($key, $requestedMediaLimits)
                && $currentValues['media_limits'][$key] !== $mergedValues['media_limits'][$key]) {
                $changedKeys[] = 'media_limits.'.$key;
            }
        }

        return $changedKeys;
    }

    /**
     * @param array{
     *     review_sla_hours: int|null,
     *     direct_publish_trust_enabled: bool,
     *     media_limits: array{
     *         max_items: int|null,
     *         max_file_size_kb: int|null,
     *         allowed_types: list<string>|null
     *     }
     * } $values
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
     *     storage_record_found: true,
     *     updated_at: string|null
     * }
     */
    private function storedSettingsContract(WorkSetting $setting, array $values): array
    {
        return [
            'scope' => WorkSetting::SCOPE_GLOBAL,
            'version' => (int) $setting->version,
            'values' => $values,
            'storage_record_found' => true,
            'updated_at' => $setting->updated_at?->toIso8601String(),
        ];
    }
}
