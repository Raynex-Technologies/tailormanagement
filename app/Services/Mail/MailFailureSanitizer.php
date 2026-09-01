<?php

namespace App\Services\Mail;

use Throwable;

class MailFailureSanitizer
{
    public function message(Throwable $exception): string
    {
        $message = strtolower($exception->getMessage());

        return match (true) {
            str_contains($message, 'authenticat'), str_contains($message, 'credential') => __('The mail server rejected the configured credentials.'),
            str_contains($message, 'certificate'), str_contains($message, 'ssl'), str_contains($message, 'tls') => __('TLS certificate verification failed. Check the server name and certificate chain.'),
            str_contains($message, 'timed out'), str_contains($message, 'timeout') => __('The mail server connection timed out.'),
            str_contains($message, 'connection'), str_contains($message, 'socket'), str_contains($message, 'refused') => __('The application could not connect to the configured mail server.'),
            default => __('The email could not be sent. Check the mail settings and try again.'),
        };
    }
}
