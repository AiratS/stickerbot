<?php

declare(strict_types=1);

namespace App\Models;

use App\Repositories\ActionRepository;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $chat_id
 * @property string $username
 * @property string $action
 * @property string $data
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @see ActionRepository
 */
class Action extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'chat_id',
        'username',
        'action',
        'data',
    ];
}
