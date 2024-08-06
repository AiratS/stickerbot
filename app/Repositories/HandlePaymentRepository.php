<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\HandlePayment;

class HandlePaymentRepository
{
    public function hasEnterAddress(int $chatId): bool
    {
        return (bool) HandlePayment::query()->where(['chat_id' => $chatId])->first();
    }

    public function create(int $chatId, string $action): void
    {
        HandlePayment::query()->create([
            'chat_id' => $chatId,
            'action' => $action,
        ]);
    }

    public function delete(int $chatId): void
    {
        /** @var HandlePayment|null $hp */
        $hp = HandlePayment::query()
            ->where(['chat_id' => $chatId])
            ->first();

        $hp?->delete();
    }
}
