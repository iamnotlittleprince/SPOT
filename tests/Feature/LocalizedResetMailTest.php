<?php

namespace Tests\Feature;

use App\Domain\Mail\PasswordResetMail;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class LocalizedResetMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_uses_requested_language_without_changing_the_account_preference(): void
    {
        Notification::fake();
        $user = User::factory()->create(['preferred_locale' => 'es']);
        $this->postJson('/forgot-password', ['email' => $user->email, 'locale' => 'en'])->assertOk();
        Notification::assertSentTo($user, ResetPassword::class, fn ($notification) => $notification->locale === 'en');
        $this->assertSame('es', $user->fresh()->preferred_locale);
    }

    public function test_account_preference_is_used_when_no_locale_is_sent_and_unknown_email_is_not_disclosed(): void
    {
        Notification::fake();
        $user = User::factory()->create(['preferred_locale' => 'es']);
        $response = $this->postJson('/forgot-password', ['email' => $user->email])->assertOk();
        Notification::assertSentTo($user, ResetPassword::class, fn ($notification) => $notification->locale === 'es');
        $this->postJson('/forgot-password', ['email' => 'unknown@example.test', 'locale' => 'es'])->assertOk()->assertExactJson($response->json());
        $this->postJson('/forgot-password', ['email' => $user->email, 'locale' => 'invalid'])->assertUnprocessable();
    }

    public function test_html_and_plain_text_email_have_localized_content_valid_link_and_configured_expiry(): void
    {
        config(['auth.passwords.users.expire' => 45]);
        $user = User::factory()->create(['email' => 'ana+test@example.test']);
        foreach (['pt' => 'Redefinir senha', 'en' => 'Reset password', 'es' => 'Restablecer contraseña'] as $locale => $button) {
            $mail = app(PasswordResetMail::class)->message($user, 'test-token', $locale);
            $html = view($mail->view['html'], $mail->viewData)->render();
            $text = view($mail->view['text'], $mail->viewData)->render();
            $this->assertStringContainsString($button, $html);
            $this->assertStringContainsString($button, $text);
            $this->assertStringContainsString('45', $html);
            $this->assertStringContainsString('lang='.$locale, $text);
            $this->assertStringNotContainsString('&amp;', $text);
            $this->assertStringContainsString('role="presentation"', $html);
            parse_str(parse_url($mail->viewData['resetUrl'], PHP_URL_QUERY), $query);
            $this->assertSame($user->email, $query['email']);
            $this->assertSame($locale, $query['lang']);
            $this->assertStringContainsString('/reset-password/test-token', $mail->viewData['resetUrl']);
        }
        $mail = app(PasswordResetMail::class)->message($user, 'test-token', 'unsupported');
        $this->assertSame('Redefina sua senha do Spot', $mail->subject);
    }

    public function test_authenticated_language_preference_can_only_update_the_current_account(): void
    {
        $first = User::factory()->create();
        $other = User::factory()->create();
        $this->patchJson('/api/v1/auth/locale', ['locale' => 'en'])->assertUnauthorized();
        $this->actingAs($first)->patchJson('/api/v1/auth/locale', ['locale' => 'en', 'user_id' => $other->id])->assertOk()->assertJsonPath('locale', 'en');
        $this->assertSame('en', $first->fresh()->preferred_locale);
        $this->assertSame('pt', $other->fresh()->preferred_locale);
        $this->patchJson('/api/v1/auth/locale', ['locale' => 'fr'])->assertUnprocessable();
    }

    public function test_localized_reset_token_still_changes_password_once(): void
    {
        Notification::fake();
        \Illuminate\Support\Facades\Mail::fake();
        $user = User::factory()->create();
        $token = null;
        $this->postJson('/forgot-password', ['email' => $user->email, 'locale' => 'es'])->assertOk();
        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use (&$token) { $token = $notification->token; return true; });
        $payload = ['email' => $user->email, 'token' => $token, 'password' => 'New-strong-password-123!', 'password_confirmation' => 'New-strong-password-123!'];
        $this->postJson('/reset-password', $payload)->assertOk();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check($payload['password'], $user->fresh()->password));
        $this->postJson('/reset-password', $payload)->assertUnprocessable();
    }

    public function test_language_from_login_is_saved_only_after_successful_authentication(): void
    {
        $user = User::factory()->create();
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'wrong', 'locale' => 'en'])->assertUnauthorized();
        $this->assertSame('pt', $user->fresh()->preferred_locale);
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password', 'locale' => 'es'])
            ->assertOk()->assertJsonPath('user.preferred_locale', 'es');
        $this->assertSame('es', $user->fresh()->preferred_locale);
    }

    public function test_native_reset_notification_uses_custom_mail_view(): void
    {
        $user = User::factory()->create();
        $message = (new ResetPassword('test-token'))->toMail($user);
        $this->assertSame('mail.password-reset', $message->view['html']);
    }
}
