<?php

namespace Lathe\Auth\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;
use Lathe\Lathe;

/**
 * Changes the password from the Security page: the current one, then a new one under the app's rules.
 *
 * @internal
 */
class UpdateUserPassword implements UpdatesUserPasswords
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function update(mixed $user, array $input): void
    {
        Validator::make($input, [
            'current_password' => ['required', 'string', 'current_password:'.Lathe::fortify()->guard()],
            'password' => ['required', 'string', Password::default(), 'confirmed'],
        ])->validate();

        if ($user instanceof Model) {
            $user->forceFill(['password' => $input['password']])->save();
        }
    }
}
