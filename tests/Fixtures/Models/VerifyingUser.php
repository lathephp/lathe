<?php

namespace Lathe\Tests\Fixtures\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Workbench\App\Models\User;

/**
 * The workbench user, made to verify its email.
 */
class VerifyingUser extends User implements MustVerifyEmail
{
    protected $table = 'users';
}
