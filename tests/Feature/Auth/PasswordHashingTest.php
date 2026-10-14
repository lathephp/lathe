<?php

use Illuminate\Support\Facades\Hash;
use Lathe\Auth\Actions\ResetUserPassword;
use Lathe\Auth\Actions\UpdateUserPassword;
use Lathe\Tests\Fixtures\Models\PlainPasswordUser;

beforeEach(function () {
    $this->loadLaravelMigrations();
    config(['auth.providers.users.model' => PlainPasswordUser::class]);
    $this->user = PlainPasswordUser::query()->create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => Hash::make('password')]);
});

it('hashes a reset password on a model without the hashed cast', function () {
    (new ResetUserPassword)->reset($this->user, ['password' => 'new-password-2026', 'password_confirmation' => 'new-password-2026']);

    $stored = $this->user->fresh()->getAttribute('password');
    expect($stored)->not->toBe('new-password-2026')
        ->and(Hash::check('new-password-2026', $stored))->toBeTrue();
});

it('hashes an updated password on a model without the hashed cast', function () {
    $this->actingAs($this->user);

    (new UpdateUserPassword)->update($this->user, ['current_password' => 'password', 'password' => 'new-password-2026', 'password_confirmation' => 'new-password-2026']);

    expect(Hash::check('new-password-2026', $this->user->fresh()->getAttribute('password')))->toBeTrue();
});
