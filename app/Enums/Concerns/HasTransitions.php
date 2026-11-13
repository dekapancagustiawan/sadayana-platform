<?php

namespace App\Enums\Concerns;

use App\Exceptions\InvalidStatusTransition;

/**
 * Lifecycle status untuk enum backed.
 *
 * Enum yang memakai trait ini mendefinisikan `allowedTransitions()`. Service
 * WAJIB memanggil `assertCanTransitionTo()` sebelum mengubah kolom status —
 * status tidak boleh diubah bebas lewat update field (Dok 06 §15).
 */
trait HasTransitions
{
    /**
     * Status tujuan yang sah dari status ini.
     *
     * @return list<self>
     */
    abstract public function allowedTransitions(): array;

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * @throws InvalidStatusTransition
     */
    public function assertCanTransitionTo(self $target): void
    {
        if (! $this->canTransitionTo($target)) {
            throw InvalidStatusTransition::between($this, $target);
        }
    }

    /**
     * Status akhir: tidak punya transisi lanjutan.
     */
    public function isFinal(): bool
    {
        return $this->allowedTransitions() === [];
    }
}
