<?php

namespace App\Services\Channel;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Support\Facades\DB;

/**
 * Resolves the customer behind a messaging-channel identity (WhatsApp).
 *
 * The channel identifier is an E.164 phone number (`62xxx`). It is normalized
 * with the shared {@see Phone} helper and mapped onto the existing `users.phone`
 * column so the WhatsApp channel reuses the same customer records as the mobile
 * app. A customer is created on first contact; subsequent messages reuse it.
 */
class ChannelCustomerService
{
    /**
     * Find (or create) the customer owning the given phone number.
     *
     * @return array{user: User, is_new: bool}
     */
    public function resolveCustomer(string $waId, ?string $displayName = null): array
    {
        $phone = Phone::normalizeTo62($waId);

        return DB::transaction(function () use ($phone, $displayName): array {
            $existing = User::query()->where('phone', $phone)->first();

            if ($existing !== null) {
                return ['user' => $existing, 'is_new' => false];
            }

            $name = $this->cleanDisplayName($displayName);

            $user = User::query()->create([
                'name' => $name,
                'email' => $this->placeholderEmail($phone),
                'phone' => $phone,
                'role' => UserRole::Customer,
            ]);

            $user->forceFill(['email_verified_at' => now()])->save();

            return ['user' => $user, 'is_new' => true];
        });
    }

    /**
     * Use the WhatsApp display name when present, otherwise a neutral fallback.
     */
    protected function cleanDisplayName(?string $displayName): string
    {
        $name = is_string($displayName) ? trim($displayName) : '';

        return $name !== '' ? $name : 'Pelanggan WhatsApp';
    }

    /**
     * Build a unique placeholder e-mail for customers created via WhatsApp.
     *
     * The `users.email` column is unique and non-nullable, so channel-only
     * customers get a deterministic address derived from their phone number.
     */
    protected function placeholderEmail(string $phone): string
    {
        $base = $phone.'@wa.rajawalitopup.local';

        if (! User::query()->where('email', $base)->exists()) {
            return $base;
        }

        return $phone.'+'.substr((string) now()->getTimestampMs(), -6).'@wa.rajawalitopup.local';
    }
}
