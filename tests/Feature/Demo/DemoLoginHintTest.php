<?php

/*
 * The live demo prints its sign-in under the login form. The server hands it to the page
 * script only when LATHE_DEMO is true, so no other copy of the workbench shows it.
 */

afterEach(fn () => putenv('LATHE_DEMO'));

it('hands the login screen the demo sign-in when LATHE_DEMO is true', function () {
    putenv('LATHE_DEMO=true');
    $this->refreshApplication();

    expect($this->get('/lathe/login')->assertOk()->viewData('page')['props']['provided'])
        ->toMatchArray(['demoLogin' => ['email' => 'admin@lathephp.com', 'password' => 'password']]);
})->group('demo');

it('keeps the sign-in off the login screen without it', function () {
    expect($this->get('/lathe/login')->assertOk()->viewData('page')['props']['provided'])
        ->not->toHaveKey('demoLogin');
})->group('demo');

it('tells the page script it runs in the demo when LATHE_DEMO is true', function () {
    putenv('LATHE_DEMO=true');
    $this->refreshApplication();

    expect($this->get('/lathe/login')->assertOk()->viewData('page')['props']['provided'])
        ->toMatchArray(['demo' => true]);
})->group('demo');

it('keeps the demo flag off the login screen without it', function () {
    expect($this->get('/lathe/login')->assertOk()->viewData('page')['props']['provided'])
        ->not->toHaveKey('demo');
})->group('demo');
