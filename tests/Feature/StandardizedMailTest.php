<?php

namespace Tests\Feature;

use App\Domain\Mail\PasswordResetMail;
use App\Mail\AccountActivationMail;
use App\Mail\ActivityNotificationMail;
use App\Mail\ProjectInvitationMail;
use App\Models\Project;
use App\Models\User;
use Tests\TestCase;

class StandardizedMailTest extends TestCase
{
    public function test_all_mail_types_share_branding_localization_safe_content_and_plain_text_links(): void
    {
        $url = 'https://example.invalid/action/token?email=ana%40example.invalid&lang=es';
        $project = new Project(['name' => '<script>alert(1)</script> Projeto & equipe']);
        foreach (['pt', 'en', 'es'] as $locale) {
            $messages = [
                new AccountActivationMail($url, '09/11/2026 18:00 UTC', $locale),
                new ProjectInvitationMail($project, $url, '<b>Ana & equipe</b>', 24, $locale),
                new ActivityNotificationMail('immediate', [['title' => '<script>alert(1)</script>', 'body' => 'Texto & conteúdo', 'actor_name' => '<b>Ana & equipe</b>']], $locale),
                new ActivityNotificationMail('daily', [], $locale),
                new ActivityNotificationMail('weekly', [], $locale),
            ];
            foreach ($messages as $mail) {
                $html = $mail->render();
                $content = $mail->content();
                $text = view($content->text, array_merge(get_object_vars($mail), $content->with))->render();
                $this->assertStringContainsString('lang="'.($locale === 'pt' ? 'pt-BR' : $locale).'"', $html);
                $this->assertStringContainsString('background:#075681', $html);
                $this->assertStringContainsString('max-width:600px', $html);
                $this->assertStringContainsString('role="presentation"', $html);
                $this->assertStringContainsString('background:#08659d', $html);
                $this->assertStringNotContainsString('<script>', $html);
                $this->assertStringNotContainsString('<b>Ana', $html);
                $this->assertStringNotContainsString('&amp;', $text);
                $this->assertStringContainsString($content->with['copy']['footer'], $text);
                $this->assertNotEmpty($mail->envelope()->subject);
                if (! $mail instanceof ActivityNotificationMail) {
                    $this->assertStringContainsString($url, $text);
                    $this->assertSame(2, substr_count($html, 'href="'.htmlspecialchars($url, ENT_QUOTES, 'UTF-8').'"'));
                }
            }
            $reset = app(PasswordResetMail::class)->message(new User(['email' => 'test@example.invalid']), 'token', $locale);
            $html = view($reset->view['html'], $reset->viewData)->render();
            $this->assertStringContainsString('background:#075681', $html);
            $this->assertStringContainsString('background:#08659d', $html);
        }
    }
}
