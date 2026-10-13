<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Lathe\Events\ServingLathe;
use Lathe\Lathe;
use Lathe\Support\TranslationManager;

beforeEach(function () {
    // The package catalogue is a fixture here, so each test controls which locales exist.
    $this->lang = sys_get_temp_dir().'/lathe-translations-'.getmypid().'-'.uniqid();
    File::makeDirectory($this->lang.'/package', 0777, true);
    File::makeDirectory($this->lang.'/app/vendor/lathe', 0777, true);
    File::put($this->lang.'/package/en.json', json_encode(['Save' => 'Save', 'Delete' => 'Delete', 'Cancel' => 'Cancel', 'Hello :name' => 'Hello :name']));
    File::put($this->lang.'/package/fr.json', json_encode(['Save' => 'Enregistrer', 'Delete' => 'Supprimer']));
    File::put($this->lang.'/package/fr-CA.json', json_encode(['Save' => 'Sauvegarder']));
    app()->useLangPath($this->lang.'/app');
    app()->instance(TranslationManager::class, new TranslationManager($this->lang.'/package'));
    $this->manager = app(TranslationManager::class);
});

afterEach(function () {
    File::deleteDirectory($this->lang);
});

it('fills :name as given, :NAME upper-cased and :Name with a capital first letter', function () {
    expect($this->manager->replace(':name :NAME :Name', ['name' => 'ada lovelace']))->toBe('ada lovelace ADA LOVELACE Ada lovelace');
});

it('matches :resources before :resource', function () {
    expect($this->manager->replace('Show :resources of :resource', ['resource' => 'order', 'resources' => 'orders']))
        ->toBe('Show orders of order');
});

it('replaces every occurrence of a placeholder', function () {
    expect($this->manager->replace(':a and :a and :A', ['a' => 'x']))->toBe('x and x and X');
});

it('returns the default, else the key, for a missing key', function () {
    expect($this->manager->get('No such key'))->toBe('No such key')
        ->and($this->manager->get('No such key', 'Fallback'))->toBe('Fallback')
        ->and(Lathe::__('No such key', [], 'Fallback'))->toBe('Fallback')
        ->and($this->manager->has('No such key'))->toBeFalse();
});

it('looks up the locale, then the language, then English', function () {
    app()->setLocale('fr-CA');

    expect($this->manager->get('Save'))->toBe('Sauvegarder')
        ->and($this->manager->get('Delete'))->toBe('Supprimer')
        ->and($this->manager->get('Cancel'))->toBe('Cancel')
        ->and(Lathe::__('Hello :name', ['name' => 'Ada']))->toBe('Hello Ada');
});

it('lets an app override in lang/vendor/lathe win over the package file', function () {
    File::put($this->lang.'/app/vendor/lathe/fr.json', json_encode(['Delete' => 'Effacer']));
    app()->setLocale('fr');

    expect($this->manager->get('Delete'))->toBe('Effacer')
        ->and($this->manager->get('Save'))->toBe('Enregistrer');
});

it('merges a file or an array over the catalogue', function () {
    File::put($this->lang.'/extra.json', json_encode(['Extra' => 'Plus']));
    $this->manager->load($this->lang.'/extra.json')->merge(['Save' => 'Garder'])->set('Other', 'Autre');

    expect($this->manager->get('Extra'))->toBe('Plus')
        ->and($this->manager->get('Save'))->toBe('Garder')
        ->and($this->manager->get('Other'))->toBe('Autre')
        ->and($this->manager->all())->toHaveKeys(['Extra', 'Save', 'Other']);
});

it('keeps a translation loaded while serving out of the worker catalogue', function () {
    Event::listen(ServingLathe::class, function (ServingLathe $event) {
        if ($event->request->headers->has('X-Load')) {
            Lathe::loadTranslations(['Per request' => 'Par requete']);
        }
    });
    Lathe::loadTranslations(['At boot' => 'Au demarrage']);

    $first = $this->get('/lathe/login', ['X-Inertia' => 'true', 'X-Load' => '1'])->json('props.translations');
    $second = $this->get('/lathe/login', ['X-Inertia' => 'true'])->json('props.translations');

    expect($first)->toHaveKey('Per request', 'Par requete')->toHaveKey('At boot', 'Au demarrage')
        ->and($second)->not->toHaveKey('Per request')->toHaveKey('At boot', 'Au demarrage')
        ->and(Lathe::__('Per request'))->toBe('Per request');
});

it('turns fr_CA into fr-CA and falls back to the app locale', function () {
    expect(Lathe::resolveUserLocale(Request::create('/')))->toBe('en');

    app()->setLocale('pt_BR');
    expect(Lathe::resolveUserLocale(Request::create('/')))->toBe('pt-BR');

    Lathe::userLocale(fn (Request $request) => 'fr_CA');
    expect(Lathe::resolveUserLocale(Request::create('/')))->toBe('fr-CA');

    Lathe::userLocale(fn (Request $request) => null);
    expect(Lathe::resolveUserLocale(Request::create('/')))->toBe('pt-BR');
});

it('sends the catalogue and locale in the once-per-session data', function () {
    Lathe::userLocale(fn () => 'fr-CA');

    $props = $this->get('/lathe/login', ['X-Inertia' => 'true'])->json('props');

    expect($props['locale'])->toBe('fr-CA')
        ->and($props['translations'])->toHaveKey('Save', 'Sauvegarder')
        ->and($this->get('/lathe/login', ['X-Inertia' => 'true', 'X-Inertia-Except-Once-Props' => 'translations,locale'])->json('props'))
        ->not->toHaveKey('translations')->not->toHaveKey('locale');
});

it('sets the request locale so validation messages follow it', function () {
    File::makeDirectory($this->lang.'/app/fr');
    File::put($this->lang.'/app/fr/validation.php', "<?php return ['required' => 'Le champ :attribute est obligatoire.'];");
    app('translator')->getLoader()->addPath($this->lang.'/app');
    Lathe::userLocale(fn () => 'fr');

    $this->get('/lathe/login')->assertOk();

    expect(validator([], ['name' => 'required'])->errors()->first('name'))->toBe('Le champ name est obligatoire.');
});
