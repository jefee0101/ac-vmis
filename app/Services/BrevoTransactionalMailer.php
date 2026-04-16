<?php

namespace App\Services;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class BrevoTransactionalMailer
{
    public function sendMailable(string $email, Mailable $mailable, ?string $name = null): void
    {
        $apiKey = trim((string) config('services.brevo.key'));
        if ($apiKey === '') {
            throw new RuntimeException('Brevo API key is missing.');
        }

        $senderEmail = trim((string) config('mail.from.address'));
        if ($senderEmail === '') {
            throw new RuntimeException('Mail from address is missing.');
        }

        $subject = $this->resolveSubject($mailable);
        $html = $mailable->render();
        $text = trim(html_entity_decode(strip_tags($html)));

        $payload = [
            'sender' => [
                'email' => $senderEmail,
                'name' => (string) config('mail.from.name'),
            ],
            'to' => [[
                'email' => $email,
                'name' => $name ?: $email,
            ]],
            'subject' => $subject,
            'htmlContent' => $html,
            'textContent' => $text !== '' ? $text : Str::limit($subject, 1000),
        ];

        $response = Http::timeout((int) config('services.brevo.timeout', 15))
            ->withHeaders([
                'api-key' => $apiKey,
                'accept' => 'application/json',
                'content-type' => 'application/json',
            ])
            ->post('https://api.brevo.com/v3/smtp/email', $payload);

        if ($response->failed()) {
            throw new RuntimeException('Brevo API request failed: ' . $response->status() . ' ' . $response->body());
        }
    }

    private function resolveSubject(Mailable $mailable): string
    {
        $envelope = $mailable->envelope();

        return trim((string) ($envelope->subject ?? 'AC-VMIS Notification')) ?: 'AC-VMIS Notification';
    }
}
