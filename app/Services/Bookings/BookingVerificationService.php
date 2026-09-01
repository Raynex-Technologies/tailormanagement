<?php

namespace App\Services\Bookings;

use App\Enums\SmsStatus;
use App\Models\OnlineBooking;
use App\Services\Sms\SmsService;
use App\Support\Phone;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class BookingVerificationService
{
    public function issue(string $wizardToken, string $purpose, string $phone, ?int $branchId, array $context = []): array
    {
        $phone = Phone::toE164Tz($phone);
        if (! $phone) {
            throw ValidationException::withMessages(['phone' => __('Enter a valid mobile number.')]);
        }

        $existing = $this->state($wizardToken, $purpose);
        if ($existing && (int) ($existing['retry_at'] ?? 0) > now()->timestamp) {
            return $existing;
        }

        $pin = (string) random_int(100000, 999999);
        $log = app(SmsService::class)->sendTemplate(
            'booking_verification',
            $phone,
            ['verification_code' => $pin],
            new OnlineBooking(['branch_id' => $branchId]),
        );

        if (in_array($log->status, [SmsStatus::Failed, SmsStatus::Skipped], true)) {
            throw ValidationException::withMessages([
                'verificationCode' => __('We could not send the verification code. Please confirm SMS settings and try again.'),
            ]);
        }

        $state = [
            'phone' => $phone,
            'hash' => Hash::make($pin),
            'expires_at' => now()->addMinutes(10)->timestamp,
            'retry_at' => now()->addSeconds(90)->timestamp,
            'attempts' => 0,
            'verified_at' => null,
            'context' => $context,
        ];
        session()->put($this->key($wizardToken, $purpose), $state);

        return $state;
    }

    public function verify(string $wizardToken, string $purpose, string $phone, string $code): array
    {
        $state = $this->state($wizardToken, $purpose);
        $phone = Phone::toE164Tz($phone);

        if (! $state || ! $phone || ! hash_equals((string) $state['phone'], $phone)) {
            throw ValidationException::withMessages(['verificationCode' => __('Request a new verification code.')]);
        }
        if ((int) $state['expires_at'] < now()->timestamp) {
            throw ValidationException::withMessages(['verificationCode' => __('This code has expired. Request a new code.')]);
        }
        if ((int) ($state['attempts'] ?? 0) >= 5) {
            $this->forget($wizardToken, $purpose);
            throw ValidationException::withMessages(['verificationCode' => __('Too many attempts. Request a new code.')]);
        }
        if (! Hash::check($code, (string) $state['hash'])) {
            $state['attempts'] = (int) ($state['attempts'] ?? 0) + 1;
            session()->put($this->key($wizardToken, $purpose), $state);
            throw ValidationException::withMessages(['verificationCode' => __('The verification code is incorrect.')]);
        }

        $state['verified_at'] = now()->timestamp;
        session()->put($this->key($wizardToken, $purpose), $state);

        return $state;
    }

    public function verified(string $wizardToken, string $purpose, string $phone, array $context = []): bool
    {
        $state = $this->state($wizardToken, $purpose);
        $phone = Phone::toE164Tz($phone);

        return $state
            && $phone
            && filled($state['verified_at'] ?? null)
            && (int) $state['expires_at'] >= now()->timestamp
            && hash_equals((string) $state['phone'], $phone)
            && collect($context)->every(fn ($value, $key) => ($state['context'][$key] ?? null) === $value);
    }

    public function state(string $wizardToken, string $purpose): ?array
    {
        $state = session()->get($this->key($wizardToken, $purpose));

        return is_array($state) ? $state : null;
    }

    public function forget(string $wizardToken, string $purpose): void
    {
        session()->forget($this->key($wizardToken, $purpose));
    }

    private function key(string $wizardToken, string $purpose): string
    {
        return 'booking_verification.'.hash('sha256', $wizardToken.'|'.$purpose);
    }
}
