<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Stable external API shape for partners; hides internal keys and normalizes dates.
 *
 * @mixin \App\Models\Order
 */
class DelayedOrderWithCaptainResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $promised = $this->promised_delivery_at;
        $now = now();

        // Business-facing "how late" metric (whole days), only meaningful when already late.
        $delayDays = ($promised && $promised->isPast())
            ? (int) $promised->diffInDays($now)
            : 0;

        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'status' => $this->status,
            'promised_delivery_at' => $promised?->toIso8601String(),
            'delay_days' => $delayDays,
            'captain' => $this->whenLoaded('captain', function () {
                return [
                    'id' => $this->captain->id,
                    'code' => $this->captain->code,
                    'full_name' => $this->captain->full_name,
                    'phone' => $this->captain->phone,
                ];
            }),
            'customer' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                ];
            }),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
