<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Models\WorkSetting;
use App\Services\Works\WorksSettingsStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use stdClass;
use Tests\TestCase;

class WorksSettingsPersistenceTest extends TestCase
{
    use RefreshDatabase;

    private WorksSettingsStore $settingsStore;

    protected function setUp(): void
    {
        parent::setUp();

        $this->settingsStore = app(WorksSettingsStore::class);
    }

    public function test_migration_creates_the_work_settings_table_contract(): void
    {
        $this->assertTrue(Schema::hasTable('work_settings'));
        $this->assertTrue(Schema::hasColumns('work_settings', [
            'id',
            'scope',
            'values',
            'version',
            'updated_by',
            'created_at',
            'updated_at',
        ]));
        $this->assertTrue(Schema::hasIndex('work_settings', ['scope'], 'unique'));

        $updatedByForeignKey = collect(Schema::getForeignKeys('work_settings'))
            ->firstWhere('columns', ['updated_by']);

        $this->assertNotNull($updatedByForeignKey);
        $this->assertSame('users', $updatedByForeignKey['foreign_table']);
        $this->assertSame('set null', strtolower((string) $updatedByForeignKey['on_delete']));
    }

    public function test_migrations_create_the_global_record(): void
    {
        $this->assertDatabaseHas('work_settings', [
            'scope' => WorkSetting::SCOPE_GLOBAL,
        ]);
        $this->assertDatabaseCount('work_settings', 1);
    }

    public function test_the_global_record_starts_with_an_empty_json_object_and_version_one(): void
    {
        $record = DB::table('work_settings')
            ->where('scope', WorkSetting::SCOPE_GLOBAL)
            ->sole();
        $decodedValues = json_decode(
            (string) $record->values,
            false,
            512,
            JSON_THROW_ON_ERROR,
        );

        $this->assertSame(WorkSetting::SCOPE_GLOBAL, $record->scope);
        $this->assertInstanceOf(stdClass::class, $decodedValues);
        $this->assertSame([], get_object_vars($decodedValues));
        $this->assertSame(1, (int) $record->version);
    }

    public function test_service_returns_defaults_for_empty_stored_values(): void
    {
        $settings = $this->settingsStore->getGlobalSettings();

        $this->assertSame($this->defaultValues(), $settings['values']);
        $this->assertTrue($settings['storage_record_found']);
        $this->assertSame(WorkSetting::SCOPE_GLOBAL, $settings['scope']);
        $this->assertSame(1, $settings['version']);
        $this->assertNotNull($settings['updated_at']);
    }

    public function test_service_returns_valid_stored_values(): void
    {
        $this->storeValues([
            'review_sla_hours' => 48,
            'direct_publish_trust_enabled' => true,
            'media_limits' => [
                'max_items' => 25,
                'max_file_size_kb' => 8192,
                'allowed_types' => ['image', 'video', 'gallery'],
            ],
        ], version: 3);

        $settings = $this->settingsStore->getGlobalSettings();

        $this->assertSame(3, $settings['version']);
        $this->assertSame([
            'review_sla_hours' => 48,
            'direct_publish_trust_enabled' => true,
            'media_limits' => [
                'max_items' => 25,
                'max_file_size_kb' => 8192,
                'allowed_types' => ['image', 'video', 'gallery'],
            ],
        ], $settings['values']);
    }

    public function test_stored_values_merge_over_defaults_without_removing_the_contract(): void
    {
        $this->storeValues(['review_sla_hours' => 12]);

        $this->assertSame([
            'review_sla_hours' => 12,
            'direct_publish_trust_enabled' => false,
            'media_limits' => [
                'max_items' => null,
                'max_file_size_kb' => null,
                'allowed_types' => null,
            ],
        ], $this->settingsStore->getGlobalSettings()['values']);
    }

    public function test_unknown_and_sensitive_keys_are_dropped(): void
    {
        $this->storeValues([
            'review_sla_hours' => 24,
            'password' => 'secret',
            'token' => 'secret-token',
            'metadata' => ['hidden' => true],
            'private_notes' => 'hidden',
            'unknown_setting' => true,
            'reporter_email' => 'reporter@example.test',
            'media_limits' => [
                'max_items' => 10,
                'unknown_nested_setting' => 'hidden',
            ],
        ]);

        $settings = $this->settingsStore->getGlobalSettings();
        $keys = $this->recursiveKeys($settings);

        foreach ([
            'password',
            'token',
            'metadata',
            'private_notes',
            'unknown_setting',
            'reporter_email',
            'unknown_nested_setting',
        ] as $forbiddenKey) {
            $this->assertNotContains($forbiddenKey, $keys);
        }
    }

    public function test_invalid_review_sla_hours_falls_back_to_null(): void
    {
        foreach ([0, 721, '24', false, 1.5, []] as $invalidValue) {
            $this->storeValues(['review_sla_hours' => $invalidValue]);

            $this->assertNull(
                $this->settingsStore->getGlobalSettings()['values']['review_sla_hours'],
            );
        }
    }

    public function test_non_boolean_direct_publish_trust_falls_back_to_false(): void
    {
        foreach (['false', '1', 0, 1, null, []] as $invalidValue) {
            $this->storeValues(['direct_publish_trust_enabled' => $invalidValue]);

            $this->assertFalse(
                $this->settingsStore->getGlobalSettings()['values']['direct_publish_trust_enabled'],
            );
        }
    }

    public function test_invalid_media_max_items_falls_back_to_null(): void
    {
        foreach ([0, 101, '10', false, 1.5] as $invalidValue) {
            $this->storeValues(['media_limits' => ['max_items' => $invalidValue]]);

            $this->assertNull(
                $this->settingsStore->getGlobalSettings()['values']['media_limits']['max_items'],
            );
        }
    }

    public function test_invalid_media_max_file_size_falls_back_to_null(): void
    {
        foreach ([0, 2097153, '8192', false, 1.5] as $invalidValue) {
            $this->storeValues(['media_limits' => ['max_file_size_kb' => $invalidValue]]);

            $this->assertNull(
                $this->settingsStore->getGlobalSettings()['values']['media_limits']['max_file_size_kb'],
            );
        }
    }

    public function test_allowed_types_drops_unknown_values_and_duplicates(): void
    {
        $this->storeValues([
            'media_limits' => [
                'allowed_types' => ['image', 'unknown', 'image', 'video', 42, null],
            ],
        ]);

        $this->assertSame(
            ['image', 'video'],
            $this->settingsStore->getGlobalSettings()['values']['media_limits']['allowed_types'],
        );

        $this->storeValues([
            'media_limits' => [
                'allowed_types' => ['unknown', 42],
            ],
        ]);

        $this->assertNull(
            $this->settingsStore->getGlobalSettings()['values']['media_limits']['allowed_types'],
        );
    }

    public function test_missing_global_record_returns_defaults_without_creating_a_record(): void
    {
        WorkSetting::query()
            ->where('scope', WorkSetting::SCOPE_GLOBAL)
            ->delete();

        $settings = $this->settingsStore->getGlobalSettings();

        $this->assertSame([
            'scope' => WorkSetting::SCOPE_GLOBAL,
            'version' => 1,
            'values' => $this->defaultValues(),
            'storage_record_found' => false,
            'updated_at' => null,
        ], $settings);
        $this->assertDatabaseCount('work_settings', 0);
    }

    public function test_service_reads_do_not_change_version_or_updated_at(): void
    {
        $setting = $this->globalSetting();
        $version = $setting->version;
        $updatedAt = $setting->updated_at?->toIso8601String();

        $this->travel(1)->minute();
        $this->settingsStore->getGlobalSettings();
        $this->settingsStore->getGlobalSettings();

        $freshSetting = $setting->fresh();

        $this->assertSame($version, $freshSetting->version);
        $this->assertSame($updatedAt, $freshSetting->updated_at?->toIso8601String());
    }

    public function test_service_contract_exposes_only_safe_top_level_fields(): void
    {
        $settings = $this->settingsStore->getGlobalSettings();

        $this->assertSame([
            'scope',
            'storage_record_found',
            'updated_at',
            'values',
            'version',
        ], collect(array_keys($settings))->sort()->values()->all());
        $this->assertArrayNotHasKey('updated_by', $settings);
        $this->assertArrayNotHasKey('updatedBy', $settings);
        $this->assertArrayNotHasKey('created_at', $settings);
        $this->assertArrayNotHasKey('raw', $settings);
        $this->assertArrayNotHasKey('model', $settings);
    }

    public function test_existing_updated_by_user_is_not_exposed(): void
    {
        $updater = User::factory()->create();
        $setting = $this->globalSetting();
        $setting->forceFill(['updated_by' => $updater->id])->save();

        $settings = $this->settingsStore->getGlobalSettings();
        $encodedSettings = strtolower(json_encode($settings, JSON_THROW_ON_ERROR));

        $this->assertSame($updater->id, $setting->fresh()->updated_by);
        $this->assertStringNotContainsString(strtolower($updater->name), $encodedSettings);
        $this->assertStringNotContainsString(strtolower($updater->email), $encodedSettings);
        $this->assertNotContains('updated_by', $this->recursiveKeys($settings));
    }

    public function test_deleted_updated_by_user_becomes_null_without_affecting_reads(): void
    {
        $updater = User::factory()->create();
        $setting = $this->globalSetting();
        $setting->forceFill([
            'updated_by' => $updater->id,
            'values' => ['review_sla_hours' => 36],
        ])->save();

        $updater->delete();

        $this->assertNull($setting->fresh()->updated_by);
        $this->assertSame(
            36,
            $this->settingsStore->getGlobalSettings()['values']['review_sla_hours'],
        );
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
     */
    private function storeValues(array $values, int $version = 1): void
    {
        $this->globalSetting()
            ->forceFill([
                'values' => $values,
                'version' => $version,
            ])
            ->save();
    }

    private function globalSetting(): WorkSetting
    {
        return WorkSetting::query()
            ->where('scope', WorkSetting::SCOPE_GLOBAL)
            ->sole();
    }

    /**
     * @return list<string>
     */
    private function recursiveKeys(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $keys = [];

        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $keys[] = strtolower($key);
            }

            $keys = [...$keys, ...$this->recursiveKeys($item)];
        }

        return $keys;
    }
}
