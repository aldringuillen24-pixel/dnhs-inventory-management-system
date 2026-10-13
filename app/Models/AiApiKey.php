<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class AiApiKey extends Model
{
    public const MAX_ACTIVE = 5;

    protected $fillable = [
        'label',
        'key_encrypted',
        'is_active',
        'priority',
        'last_used_at',
        'last_error',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
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
        return [
            'id' => $this->id,
            'label' => $this->label,
            'masked_key' => $this->maskedKey(),
            'is_active' => (bool) $this->is_active,
            'priority' => (int) $this->priority,
            'last_used_at' => $this->last_used_at?->toDateTimeString(),
            'last_error' => $this->last_error,
        ];
    }
}
