<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Support\UsernamePolicy;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UsernameFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_user_without_username_can_login_by_legacy_email_field(): void
    {
        $user = $this->userWithPassword();

        $this->assertNull($user->username);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'correct-password',
        ])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_user_can_login_by_username(): void
    {
        $this->userWithPassword(['username' => 'motion']);

        $this->postJson('/api/auth/login', [
            'login' => 'motion',
            'password' => 'correct-password',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['token']]);
    }

    public function test_login_field_accepts_an_email_identifier(): void
    {
        $user = $this->userWithPassword();

        $this->postJson('/api/auth/login', [
            'login' => $user->email,
            'password' => 'correct-password',
        ])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_login_by_username_is_case_insensitive_after_normalization(): void
    {
        $this->userWithPassword(['username' => 'motion']);

        $this->postJson('/api/auth/login', [
            'login' => 'Motion',
            'password' => 'correct-password',
        ])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_failed_login_uses_the_same_generic_message_for_existing_and_unknown_identifiers(): void
    {
        $this->userWithPassword(['username' => 'known-user']);

        $existing = $this->postJson('/api/auth/login', [
            'login' => 'known-user',
            'password' => 'wrong-password',
        ])->assertUnauthorized();

        $unknown = $this->postJson('/api/auth/login', [
            'login' => 'missing-user',
            'password' => 'wrong-password',
        ])->assertUnauthorized();

        $this->assertSame('بيانات الدخول غير صحيحة.', $existing->json('message'));
        $this->assertSame($existing->json('message'), $unknown->json('message'));
    }

    public function test_disabled_user_cannot_login_by_username(): void
    {
        $user = $this->userWithPassword([
            'username' => 'disabled-user',
            'disabled_at' => now(),
        ]);

        $this->postJson('/api/auth/login', [
            'login' => 'DISABLED-USER',
            'password' => 'correct-password',
        ])
            ->assertForbidden()
            ->assertJsonPath('success', false);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
        ]);
    }

    public function test_username_is_stored_as_trimmed_lowercase(): void
    {
        $user = User::factory()->create(['username' => '  Motion-Studio  ']);

        $this->assertSame('motion-studio', $user->username);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'username' => 'motion-studio',
        ]);
    }

    public function test_username_normalization_converts_blank_values_to_null(): void
    {
        $this->assertNull(UsernamePolicy::normalize(null));
        $this->assertNull(UsernamePolicy::normalize(''));
        $this->assertNull(UsernamePolicy::normalize('   '));
        $this->assertSame('motion', UsernamePolicy::normalize(' Motion '));
    }

    public function test_multiple_blank_usernames_are_stored_as_null_without_unique_conflict(): void
    {
        $empty = User::factory()->create(['username' => '']);
        $spaces = User::factory()->create(['username' => '   ']);

        $this->assertNull($empty->username);
        $this->assertNull($spaces->username);
        $this->assertSame(2, User::query()->whereNull('username')->count());
    }

    public function test_normal_usernames_are_accepted_by_the_central_policy(): void
    {
        foreach (['motion', 'motion26', 'motion_ye', 'motion-studio'] as $username) {
            $this->assertTrue(
                UsernamePolicy::isValid($username),
                "Expected [{$username}] to be accepted.",
            );
        }
    }

    public function test_invalid_usernames_are_rejected_by_the_central_policy(): void
    {
        $invalidUsernames = [
            'abc',
            str_repeat('a', 25),
            '1234',
            '-motion',
            'motion_',
            'motion--studio',
            'motion__studio',
            'motion-_studio',
            'motion_-studio',
            'موشن',
            'motion studio',
            'motion!',
        ];

        foreach ($invalidUsernames as $username) {
            $this->assertFalse(
                UsernamePolicy::isValid($username),
                "Expected [{$username}] to be rejected.",
            );
        }
    }

    public function test_reserved_names_are_rejected_after_normalization(): void
    {
        foreach (['admin', 'ADMIN', ' yemen-motion ', 'verified'] as $username) {
            $this->assertFalse(
                UsernamePolicy::isValid($username),
                "Expected reserved name [{$username}] to be rejected.",
            );
        }
    }

    public function test_privileged_length_requires_explicit_non_public_opt_in(): void
    {
        $this->assertFalse(UsernamePolicy::isValid('ym'));
        $this->assertFalse(UsernamePolicy::isValid('ymc'));
        $this->assertTrue(UsernamePolicy::isValid('ym', allowPrivileged: true));
        $this->assertTrue(UsernamePolicy::isValid('ymc', allowPrivileged: true));
        $this->assertFalse(UsernamePolicy::isValid('y', allowPrivileged: true));
    }

    public function test_canonical_lowercase_uniqueness_prevents_case_variants(): void
    {
        User::factory()->create(['username' => 'Motion']);

        $this->expectException(QueryException::class);

        User::factory()->create(['username' => 'motion']);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function userWithPassword(array $attributes = []): User
    {
        return User::factory()->create([
            ...$attributes,
            'password' => Hash::make('correct-password'),
        ]);
    }
}
