<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class AiApiKey extends Model
{
    public const MAX_ACTIVE = 5;

    /**
     * System providers that can hold pooled keys. Gemini rows drive the
     * AI failover pool; further providers reserve pool rows for future
     * wiring. Keep in sync with the provider select in Settings.vue
     * (AI Config tab) and the storeKey/updateKey validation below.
     */
    public const PROVIDERS = [
        'gemini',
        'forecast',
    ];

    protected $fillable = [
        'provider',
        'label',
        'key_encrypted',
        'is_active',
        'priority',
        'last_used_at',
        'last_error',
        'last_tokens_sent',
        'last_ping_ok',
        'last_checked_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
        'last_tokens_sent' => 'integer',
        'last_ping_ok' => 'boolean',
        'last_checked_at' => 'datetime',
    ];

    /**
     * Store a plaintext key encrypted. Never persist plaintext.
     */
    public static function encryptKey(string $plaintext): string
    {
        return Crypt::encryptString($plaintext);
    }

    public function decryptedKey(): string
    {
        return Crypt::decryptString($this->key_encrypted);
    }

    /**
     * Masked form for API responses, e.g. "…Ab12". Never the full key.
     */
    public function maskedKey(): string
    {
        try {
            $plain = $this->decryptedKey();
        } catch (\Throwable) {
            return '…????';
        }

        return '…'.substr($plain, -4);
    }

    public function toMaskedArray(): array
    {
        // Raw attributes: on pre-migration schemas these columns do not
        // exist, and the boolean cast would coerce the missing value to
        // false (a phantom ping-fail). Null stays null (unknown).
        $raw = $this->getAttributes();

        return [
            'id' => $this->id,
            'provider' => $this->provider ?? 'gemini',
            'label' => $this->label,
            'masked_key' => $this->maskedKey(),
            'is_active' => (bool) $this->is_active,
            'priority' => (int) $this->priority,
            'last_used_at' => $this->last_used_at?->toDateTimeString(),
            'last_error' => $this->last_error,
            // Last probe outcome. Token counts come from the provider's
            // usageMetadata and are null when it reports none.
            'last_tokens_sent' => ($raw['last_tokens_sent'] ?? null) !== null ? (int) $raw['last_tokens_sent'] : null,
            'last_ping_ok' => ($raw['last_ping_ok'] ?? null) !== null ? (bool) $raw['last_ping_ok'] : null,
            'last_checked_at' => $this->last_checked_at?->toDateTimeString(),
        ];
    }
}
