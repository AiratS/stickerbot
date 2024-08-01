<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Action;

/**
 * @see Action
 */
class ActionRepository
{
    public function save(int $chatId, string $username, string $action, ?string $data = null): void
    {
        Action::query()->create([
            'chat_id' => $chatId,
            'username' => $username,
            'action' => $action,
            'data' => $data,
        ]);
    }
}
