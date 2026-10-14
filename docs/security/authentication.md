---
title: Authentication
description: Sign people in to the panel with Laravel Fortify, and turn on password reset, email verification and two-factor authentication.
order: 1
---

# Authentication

Lathe signs people in with [Laravel Fortify](https://laravel.com/docs/fortify). The panel serves its own login page and the screens around it. It keeps its Fortify settings apart from your app: Lathe sets them for panel requests only and puts yours back afterwards. If your app also uses Fortify, nothing in this page changes it.

Signing in is not enough to open the panel. The user must also pass the `viewLathe` gate. See [Policies](policies.md).

## Sign in

Open `/lathe` while signed out and the panel sends you to `/lathe/login`. The page asks for an email and a password and has a "Remember me" box. After a good sign-in, the user goes back to the page they first asked for. If there was none, they go to the panel root.

Fortify limits sign-in to five tries a minute for each username and IP address. The sixth try answers 429 with "Too many login attempts."

To send people somewhere else after sign-in, call `initialPath()` from your `LatheServiceProvider`. It takes a path or a closure that gets the request:

```php
use Illuminate\Http\Request;
use Lathe\Lathe;

Lathe::initialPath(fn (Request $request) => '/lathe/resources/orders');
```

You can also set `initial_path` in `config/lathe.php`. The method wins over the config key.

To sign in with a field other than `email`, set `fortify.username` in `config/lathe.php`. Set `fortify.email` to name the field that password reset reads the address from. Both default to `email`.

## Guard and password broker

The panel uses your app's default guard and default password broker. To use others, set them in `config/lathe.php` or in `.env`:

```bash
LATHE_GUARD=staff
LATHE_PASSWORDS=staff
```

The guard must be a session guard, and its user provider must name an Eloquent model. Lathe reads the user model from that provider. The same guard decides who is signed in for every panel route, so a user signed in on another guard does not count.

## Password reset

Reset is off by default. Turn it on from your `LatheServiceProvider`:

```php
use Lathe\Lathe;

Lathe::withPasswordResetRoutes();
```

The login page then shows "Forgot your password?". It leads to `/lathe/forgot-password`, where the user enters an email address. The answer is always "We have emailed your password reset link", even for an address you do not know. That stops people from using the form to find out which addresses have accounts.

The mail goes through your app's mailer, and its link opens `/lathe/reset-password/{token}`. The token life and the throttle come from the `auth.passwords` settings of your app. A new password must pass `Password::default()`.

> Note: With the `log` mail driver, which is the default in a new Laravel app, no mail is sent. Open `storage/logs/laravel.log` and copy the reset link from the logged message.

`withoutPasswordResetRoutes()` turns the screens off again. It takes the paths of your own forgot and reset pages, and the login page links to them:

```php
Lathe::withoutPasswordResetRoutes('/forgot-password', '/reset-password');
```

## Create your first user

The command `lathe:user` creates a user who can sign in. See [Commands](../getting-started/commands.md). A new user still sees a 403 page until the `viewLathe` gate lets them in. See [Installation](../getting-started/installation.md).

## Fortify features

Three Fortify features add screens to the panel. Turn them on with a comma list in `.env`:

```bash
LATHE_FORTIFY_FEATURES=update-passwords,two-factor-authentication,email-verification
```

This fills `fortify.features` in `config/lathe.php`. The default is none. When a feature needs options, write the array in the config file with Fortify's `Features` calls in place of the env list:

```php
'fortify' => [
    'features' => [
        Features::updatePasswords(),
        Features::twoFactorAuthentication(['confirm' => true, 'confirmPassword' => true]),
        Features::emailVerification(),
    ],
],
```

Import `Laravel\Fortify\Features` at the top of the file.

## Email verification

Turn on `email-verification` and make your user model implement `Illuminate\Contracts\Auth\MustVerifyEmail`. A user whose address is not verified is held on the notice page at `/lathe/email/verify` until they follow the signed link in their mail. The page has a button to send the mail again, which is limited to six a minute, and a button to sign out. API calls from an unverified user answer 403 "Your email address is not verified."

## Two-factor authentication

Turn on `two-factor-authentication` and add Fortify's `TwoFactorAuthenticatable` trait to your user model. Users then manage two-factor on the [Security page](#the-security-page). Lathe supports time-based codes from an authenticator app. Email codes are not part of 1.0.

Two options change how it works:

- `confirm` makes enabling finish only when the user enters a valid code. Until then, two-factor is pending and does not guard sign-in.
- `confirmPassword` asks the user to confirm their password before they enable, disable or read codes. See [Password confirmation](#password-confirmation).

### Set up

On the Security page the user presses "Enable". With `confirm` on, the page then shows a QR code and the secret key. The user scans the code with an authenticator app, types the six digits it shows, and presses confirm. Only then is two-factor on.

### Recovery codes

Once two-factor is on, the page offers eight recovery codes behind "Show recovery codes". "Regenerate recovery codes" replaces all eight. Each code works once. Tell users to store them somewhere safe, because a lost device and no codes locks the account.

### Sign in with two-factor

A user with confirmed two-factor lands on `/lathe/two-factor-challenge` after the right password. They type the six-digit code. "Use a recovery code" swaps the field for a recovery code. The challenge allows five tries a minute.

## Password confirmation

Some requests need a recent password check. When one does, the panel opens a dialog that asks for the password, then repeats the request. The same form exists as a page at `/lathe/confirm-password`. A confirmation stays valid for the time in your `auth.password_timeout` setting, three hours by default.

## The Security page

The user menu shows "Security" when password updates or two-factor is on. It opens `/lathe/security`, which has a card for each feature that is on:

- Password: current password, new password and confirmation. A good change shows "Password updated."
- Two-factor: the state of two-factor and the buttons for the steps above.

## Change the login logic

`Lathe::fortify()` returns the panel's Fortify settings. Three methods change how sign-in works. Each takes a callable, and each is used for panel requests only:

```php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Lathe\Lathe;

// Check the credentials yourself. Return the user, or null to fail.
Lathe::fortify()->authenticateUsing(function (Request $request) {
    $user = \App\Models\User::where('email', $request->input('email'))->first();

    return $user && Hash::check($request->input('password'), $user->password) && $user->active
        ? $user
        : null;
});

// Replace the list of steps in the login pipeline.
Lathe::fortify()->authenticateThrough(fn (Request $request) => [
    \App\Auth\EnsureAccountIsActive::class,
    \Laravel\Fortify\Actions\AttemptToAuthenticate::class,
    \Laravel\Fortify\Actions\PrepareAuthenticatedSession::class,
]);

// Decide whether a typed password matches, for password confirmation.
Lathe::fortify()->confirmPasswordsUsing(fn ($user, ?string $password) => Hash::check((string) $password, $user->password));
```

Call them in the `authorization()` method of your `LatheServiceProvider`, or in `boot()`.

## Use your own login

If your app already has a login page, turn the panel's login and logout routes off and tell Lathe where yours are:

```php
use Lathe\Lathe;

Lathe::withoutAuthenticationRoutes(login: '/login', logout: '/logout');
```

The panel then sends visitors to those paths when they are not signed in. It uses your guard to find the user, and the `viewLathe` gate still decides who gets in. Call `Lathe::withAuthenticationRoutes()` to go back to the panel's own routes, which is the default.

Both paths are optional. A signed-out visitor with no login path goes to `/login`.

## Middleware

Panel pages run the groups in the `middleware` setting. Panel API calls run `api_middleware`, which holds two classes:

- `Lathe\Http\Middleware\Authenticate` lets in only a user signed in on the panel guard. A signed-out API call gets a 401 JSON answer, and a page visit goes to the login page.
- `Lathe\Http\Middleware\Authorize` refuses a signed-in user who does not pass `Lathe::check()` with a 403.

`Lathe::auth()` replaces the `viewLathe` check with your own callback. See [Policies](policies.md#the-viewlathe-gate).

## What you see

A signed-out visit to `/lathe` shows a centred login card on the panel's own page. After you sign in, the panel opens at its root. With the features on, the user menu holds a "Security" entry that opens the page for password and two-factor, and a signed-in user with two-factor on sees the code screen after the password.
