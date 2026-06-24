<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_existing_email_returns_generic_success(): void
    {
        $user = User::factory()->create([
            'email' => 'existing@example.com',
        ]);

        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'existing@example.com',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => null,
            'message' => 'إذا كان البريد مسجلاً لدينا، فسيتم إرسال رابط استعادة كلمة المرور.',
            'errors' => null,
        ]);
    }

    public function test_forgot_password_unknown_email_returns_same_generic_success(): void
    {
        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'nonexistent@example.com',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => null,
            'message' => 'إذا كان البريد مسجلاً لدينا، فسيتم إرسال رابط استعادة كلمة المرور.',
            'errors' => null,
        ]);
    }

    public function test_reset_password_valid_token_changes_password(): void
    {
        $user = User::factory()->create([
            'email' => 'resetuser@example.com',
        ]);
        $user->password = 'oldpassword123';
        $user->save();

        $token = Password::createToken($user);

        $response = $this->postJson('/api/auth/reset-password', [
            'email' => 'resetuser@example.com',
            'token' => $token,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => null,
            'message' => 'تم تغيير كلمة المرور بنجاح.',
            'errors' => null,
        ]);

        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => 'resetuser@example.com',
            'password' => 'newpassword123',
        ]);
        $loginResponse->assertStatus(200);
        $loginResponse->assertJson(['success' => true]);
        $this->assertNotEmpty($loginResponse->json('data.token'));
    }

    public function test_reset_password_invalid_token_returns_422(): void
    {
        User::factory()->create([
            'email' => 'invalidtoken@example.com',
        ]);

        $response = $this->postJson('/api/auth/reset-password', [
            'email' => 'invalidtoken@example.com',
            'token' => 'invalid-token-12345',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'data' => null,
            'message' => 'رابط استعادة كلمة المرور غير صالح أو منتهي الصلاحية.',
        ]);
    }

    public function test_reset_password_validation_requires_all_fields(): void
    {
        $response = $this->postJson('/api/auth/reset-password', [
            'email' => 'incomplete@example.com',
        ]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'errors' => ['token', 'password']]);
    }

    public function test_password_reset_revokes_existing_sanctum_tokens(): void
    {
        // إنشاء مستخدم مع كلمة مرور ومفتاح توكن موجود مسبقًا
        $user = User::factory()->create([
            'email' => 'token-reset@example.com',
            'password' => 'old-password',
        ]);
        // إنشاء توكن قبلي
        $user->createToken('existing-token');

        // التأكد من وجود التوكن في قاعدة البيانات
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_type' => User::class,
            'tokenable_id' => $user->id,
            'name' => 'existing-token',
        ]);

        // إنشاء رمز إعادة تعيين كلمة المرور الصالح
        $resetToken = Password::createToken($user);

        // طلب إعادة تعيين كلمة المرور
        $response = $this->postJson('/api/auth/reset-password', [
            'email' => $user->email,
            'token' => $resetToken,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertOk();

        // بعد إعادة التعيين يجب ألا يبقى أي توكن للـ Sanctum
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_type' => User::class,
            'tokenable_id' => $user->id,
        ]);
    }
    public function test_old_password_no_longer_works_after_reset(): void
    {
        $user = User::factory()->create([
            'email' => 'oldpass@example.com',
        ]);
        $user->password = 'originalpass123';
        $user->save();

        $token = Password::createToken($user);

        $this->postJson('/api/auth/reset-password', [
            'email' => 'oldpass@example.com',
            'token' => $token,
            'password' => 'brandnewpass456',
            'password_confirmation' => 'brandnewpass456',
        ]);

        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => 'oldpass@example.com',
            'password' => 'originalpass123',
        ]);
        $loginResponse->assertStatus(401);
    }
}


