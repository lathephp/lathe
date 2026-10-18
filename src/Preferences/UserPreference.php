<?php

namespace Lathe\Preferences;

use Illuminate\Database\Eloquent\Model;
use Lathe\Http\Requests\LatheRequest;

/**
 * One saved choice of one user, such as their columns of a Resource index under columns:<uriKey>.
 *
 * @property int $id
 * @property string $user_id
 * @property string $key
 * @property array<array-key, mixed> $value
 */
class UserPreference extends Model
{
    protected $table = 'lathe_user_preferences';

    /**
     * @var list<string>
     */
    protected $fillable = ['user_id', 'key', 'value'];

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    /**
     * The signed-in user's id as stored, the way saved tabs store it.
     */
    public static function userId(LatheRequest $request): string
    {
        $id = $request->user()?->getAuthIdentifier();

        return is_scalar($id) ? (string) $id : '';
    }
}
