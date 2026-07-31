<?php

namespace Tests\Feature\Designer;

use App\Models\DesignerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DesignerProfileBootstrapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['designer', 'client', 'staff'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    public function test_guest_is_denied(): void
    {
        $this->getJson('/api/designer/profile')->assertUnauthorized();
    }

    public function test_client_is_denied(): void
    {
        Sanctum::actingAs($this->userWithRole('client'));

        $this->getJson('/api/designer/profile')->assertForbidden();
    }

    public function test_staff_without_designer_role_is_denied(): void
    {
        Sanctum::actingAs($this->userWithRole('staff'));

        $this->getJson('/api/designer/profile')->assertForbidden();
    }

    public function test_disabled_designer_is_denied(): void
    {
        $designer = $this->userWithRole('designer', ['disabled_at' => now()]);
        $token = $designer->createToken('designer-profile-test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/designer/profile')
            ->assertForbidden();
    }

    public function test_designer_get_before_creation_returns_null_profile(): void
    {
        $designer = $this->userWithRole('designer');
        Sanctum::actingAs($designer);

        $this->getJson('/api/designer/profile')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.profile', null)
            ->assertJsonPath('data.username', null)
            ->assertJsonPath('data.can_claim_username', true)
            ->assertJsonPath('data.basic_completion.total', 5);
    }

    public function test_designer_creates_own_profile_and_claims_lowercase_username(): void
    {
        $designer = $this->userWithRole('designer');
        Sanctum::actingAs($designer);

        $this->putJson('/api/designer/profile', $this->payload(['username' => ' Motion-Studio ']))
            ->assertOk()
            ->assertJsonPath('data.profile.display_name', 'مصمم موشن')
            ->assertJsonPath('data.username', 'motion-studio')
            ->assertJsonPath('data.profile.publication_status', 'draft');

        $this->assertDatabaseHas('designer_profiles', [
            'user_id' => $designer->id,
            'display_name' => 'مصمم موشن',
            'publication_status' => 'draft',
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $designer->id,
            'username' => 'motion-studio',
        ]);
    }

    public function test_profile_is_scoped_to_current_designer_only(): void
    {
        $first = $this->userWithRole('designer');
        $second = $this->userWithRole('designer');
        $second->designerProfile()->create($this->profileAttributes(['display_name' => 'المصمم الثاني']));
        Sanctum::actingAs($first);

        $this->getJson('/api/designer/profile')
            ->assertOk()
            ->assertJsonPath('data.profile', null);

        $this->putJson('/api/designer/profile', $this->payload(['username' => 'first-designer']))
            ->assertOk()
            ->assertJsonPath('data.profile.display_name', 'مصمم موشن');

        $this->assertDatabaseHas('designer_profiles', [
            'user_id' => $second->id,
            'display_name' => 'المصمم الثاني',
        ]);
        $this->assertDatabaseHas('designer_profiles', [
            'user_id' => $first->id,
            'display_name' => 'مصمم موشن',
        ]);
    }

    public function test_second_put_updates_the_same_profile(): void
    {
        $designer = $this->userWithRole('designer');
        Sanctum::actingAs($designer);

        $this->putJson('/api/designer/profile', $this->payload(['username' => 'same-profile']))
            ->assertOk();
        $this->putJson('/api/designer/profile', $this->payload([
            'username' => 'same-profile',
            'display_name' => 'الاسم المحدث',
        ]))
            ->assertOk()
            ->assertJsonPath('data.profile.display_name', 'الاسم المحدث');

        $this->assertSame(1, DesignerProfile::query()->where('user_id', $designer->id)->count());
    }

    public function test_existing_username_may_be_omitted(): void
    {
        $designer = $this->userWithRole('designer', ['username' => 'existing-name']);
        Sanctum::actingAs($designer);
        $payload = $this->payload();
        unset($payload['username']);

        $this->putJson('/api/designer/profile', $payload)
            ->assertOk()
            ->assertJsonPath('data.username', 'existing-name');
    }

    public function test_creation_without_availability_uses_unavailable(): void
    {
        $designer = $this->userWithRole('designer');
        Sanctum::actingAs($designer);
        $payload = $this->payload(['username' => 'without-availability']);
        unset($payload['availability']);

        $this->putJson('/api/designer/profile', $payload)
            ->assertOk()
            ->assertJsonPath('data.profile.availability', DesignerProfile::AVAILABILITY_UNAVAILABLE)
            ->assertJsonPath('data.profile.publication_status', 'draft')
            ->assertJsonPath('data.basic_completion.total', 5);
    }

    public function test_update_without_availability_preserves_current_value(): void
    {
        $designer = $this->userWithRole('designer', ['username' => 'preserved-availability']);
        $designer->designerProfile()->create($this->profileAttributes(['availability' => 'partially_available']));
        Sanctum::actingAs($designer);
        $payload = $this->payload(['display_name' => 'اسم محدث']);
        unset($payload['username']);
        unset($payload['availability']);

        $this->putJson('/api/designer/profile', $payload)
            ->assertOk()
            ->assertJsonPath('data.profile.availability', 'partially_available')
            ->assertJsonPath('data.profile.display_name', 'اسم محدث');
    }

    public function test_legacy_payload_with_availability_remains_supported(): void
    {
        $designer = $this->userWithRole('designer');
        Sanctum::actingAs($designer);
        $this->putJson('/api/designer/profile', $this->payload([
            'username' => 'legacy-availability',
            'availability' => 'available',
        ]))
            ->assertOk()
            ->assertJsonPath('data.profile.availability', 'available')
            ->assertJsonPath('data.basic_completion.total', 5)
            ->assertJsonPath('data.profile.publication_status', 'draft');
    }

    public function test_changing_existing_username_is_rejected(): void
    {
        $designer = $this->userWithRole('designer', ['username' => 'original-name']);
        Sanctum::actingAs($designer);

        $this->putJson('/api/designer/profile', $this->payload(['username' => 'different-name']))
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.username.0',
                'لا يمكن تغيير اسم المستخدم من هذه الشاشة.',
            );
    }

    public function test_invalid_normal_username_is_rejected(): void
    {
        $designer = $this->userWithRole('designer');
        Sanctum::actingAs($designer);

        foreach (['1234', '-motion', 'motion--studio', 'موشن'] as $username) {
            $this->putJson('/api/designer/profile', $this->payload(['username' => $username]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('username');
        }
    }

    public function test_reserved_username_is_rejected(): void
    {
        Sanctum::actingAs($this->userWithRole('designer'));

        $this->putJson('/api/designer/profile', $this->payload(['username' => 'admin']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('username');
    }

    public function test_privileged_two_or_three_character_username_is_rejected(): void
    {
        Sanctum::actingAs($this->userWithRole('designer'));

        foreach (['ym', 'ymc'] as $username) {
            $this->putJson('/api/designer/profile', $this->payload(['username' => $username]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('username');
        }
    }

    public function test_duplicate_username_is_rejected(): void
    {
        User::factory()->create(['username' => 'claimed-name']);
        Sanctum::actingAs($this->userWithRole('designer'));

        $this->putJson('/api/designer/profile', $this->payload(['username' => 'CLAIMED-NAME']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('username');
    }

    public function test_username_availability_reports_normalized_reserved_and_existing_states(): void
    {
        $designer = $this->userWithRole('designer');
        User::factory()->create(['username' => 'taken-name']);
        Sanctum::actingAs($designer);

        $this->getJson('/api/designer/profile/username-availability?username=Motion_Name')
            ->assertOk()
            ->assertJsonPath('data.available', true)
            ->assertJsonPath('data.normalized', 'motion_name')
            ->assertJsonPath('data.reason', null);

        $this->getJson('/api/designer/profile/username-availability?username=admin')
            ->assertOk()
            ->assertJsonPath('data.available', false)
            ->assertJsonPath('data.normalized', 'admin')
            ->assertJsonPath('data.reason', 'reserved');

        $this->getJson('/api/designer/profile/username-availability?username=TAKEN-NAME')
            ->assertOk()
            ->assertJsonPath('data.available', false)
            ->assertJsonPath('data.normalized', 'taken-name')
            ->assertJsonPath('data.reason', 'taken');
    }

    public function test_availability_excludes_the_current_users_username(): void
    {
        $designer = $this->userWithRole('designer', ['username' => 'current-name']);
        Sanctum::actingAs($designer);

        $this->getJson('/api/designer/profile/username-availability?username=CURRENT-NAME')
            ->assertOk()
            ->assertJsonPath('data.available', true)
            ->assertJsonPath('data.normalized', 'current-name');
    }

    public function test_basic_completion_is_calculated_from_five_fields(): void
    {
        Sanctum::actingAs($this->userWithRole('designer'));

        $this->putJson('/api/designer/profile', $this->payload([
            'username' => 'completion-name',
            'professional_title' => null,
            'primary_specialty' => null,
            'bio' => null,
        ]))
            ->assertOk()
            ->assertJsonPath('data.basic_completion.completed', 2)
            ->assertJsonPath('data.basic_completion.total', 5)
            ->assertJsonPath('data.basic_completion.percentage', 40)
            ->assertJsonPath('data.basic_completion.missing', [
                'professional_title',
                'primary_specialty',
                'bio',
            ]);
    }

    public function test_publication_status_remains_draft(): void
    {
        $designer = $this->userWithRole('designer');
        Sanctum::actingAs($designer);

        $this->putJson('/api/designer/profile', $this->payload(['username' => 'draft-profile']))
            ->assertOk()
            ->assertJsonPath('data.profile.publication_status', 'draft');

        $this->assertDatabaseHas('designer_profiles', [
            'user_id' => $designer->id,
            'publication_status' => DesignerProfile::PUBLICATION_DRAFT,
            'published_at' => null,
        ]);
    }

    public function test_no_public_profile_route_is_created(): void
    {
        $this->getJson('/api/designers/not-created')->assertNotFound();
    }

    public function test_response_does_not_expose_private_user_fields(): void
    {
        $designer = $this->userWithRole('designer', ['username' => 'private-safe']);
        $designer->designerProfile()->create($this->profileAttributes());
        Sanctum::actingAs($designer);

        $response = $this->getJson('/api/designer/profile')->assertOk();
        $json = json_encode($response->json(), JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString($designer->email, $json);
        $this->assertStringNotContainsString('password', $json);
        $this->assertStringNotContainsString('remember_token', $json);
        $this->assertStringNotContainsString('disabled_at', $json);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function userWithRole(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole($role);

        return $user;
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'username' => 'motion-profile',
            'display_name' => 'مصمم موشن',
            'professional_title' => 'مصمم جرافيك',
            'primary_specialty' => 'الهوية البصرية',
            'bio' => 'مصمم يهتم ببناء هويات بصرية واضحة ومهنية.',
            'availability' => 'available',
            ...$overrides,
        ];
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function profileAttributes(array $overrides = []): array
    {
        return [
            'display_name' => 'مصمم موشن',
            'professional_title' => 'مصمم جرافيك',
            'primary_specialty' => 'الهوية البصرية',
            'bio' => 'نبذة مهنية.',
            'availability' => 'available',
            ...$overrides,
        ];
    }
}
