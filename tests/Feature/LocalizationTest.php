<?php

use App\Models\Site;
use App\Models\User;
use App\Support\Messages;
use App\Support\Plural;
use App\Support\TranslationKeys;

test('every language file has a translation for every string', function () {
    $keys = TranslationKeys::all();
    expect(count($keys))->toBeGreaterThan(500);

    foreach (array_keys(config('locales')) as $locale) {
        if ($locale === 'en') {
            continue;
        }
        $path = lang_path("{$locale}.json");
        expect(file_exists($path))->toBeTrue("lang/{$locale}.json is missing");

        $translations = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        $missing = array_values(array_filter($keys, fn ($key) => ! isset($translations[$key]) || trim($translations[$key]) === ''));
        expect($missing)->toBe([], "lang/{$locale}.json is missing: ".implode(' | ', array_slice($missing, 0, 5)));

        foreach ($translations as $key => $value) {
            preg_match_all('/\{(\w+)/', $key, $wanted);
            preg_match_all('/\{(\w+)/', $value, $found);
            expect(array_diff($wanted[1], $found[1]))->toBe([], "{$locale}: placeholders lost in “{$key}”");
            Messages::format($value, array_fill_keys($wanted[1], 2), $locale);
        }
    }
});

test('plural rules follow CLDR', function () {
    expect(Plural::category('en', 1))->toBe('one')
        ->and(Plural::category('en', 2))->toBe('other')
        ->and(Plural::category('ru', 1))->toBe('one')
        ->and(Plural::category('ru', 3))->toBe('few')
        ->and(Plural::category('ru', 11))->toBe('many')
        ->and(Plural::category('pl', 22))->toBe('few')
        ->and(Plural::category('pl', 25))->toBe('many')
        ->and(Plural::category('ga', 2))->toBe('two')
        ->and(Plural::category('cs', 4))->toBe('few')
        ->and(Plural::category('lv', 0))->toBe('zero')
        ->and(Plural::category('bn', 1))->toBe('one');
});

test('tr formats plurals and placeholders in the requested language', function () {
    expect(tr('{count, plural, one {# page} other {# pages}}', ['count' => 1]))->toBe('1 page')
        ->and(tr('{count, plural, one {# page} other {# pages}}', ['count' => 4]))->toBe('4 pages')
        ->and(tr('Scan #{id}', ['id' => 7], 'de'))->toContain('7')
        ->and(tr('Settings', [], 'bn'))->toBe('সেটিংস');
});

test('the browser language is used for signed-out visitors', function () {
    $this->get('/login', ['Accept-Language' => 'fr-FR,fr;q=0.9'])
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('locale', 'fr')->has('locales', 26));
});

test('choosing a language saves it on the account', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/locale', ['locale' => 'bn'])->assertRedirect()->assertCookie('locale', 'bn');
    expect($user->fresh()->locale)->toBe('bn');

    $this->actingAs($user->fresh())->get('/settings/profile')->assertInertia(fn ($page) => $page->where('locale', 'bn'));
});

test('unsupported languages are rejected', function () {
    $this->post('/locale', ['locale' => 'xx'])->assertSessionHasErrors('locale');
});

test('the public statement and the report use the site language', function () {
    $site = Site::factory()->create(['language' => 'de']);

    $this->actingAs($site->user)->get("/sites/{$site->id}/statement")->assertOk();
    $statement = $site->statement()->first();
    expect($statement->feedback_process)->toContain('Feedback');
    $statement->update(['organisation' => 'Beispiel GmbH', 'contact_email' => 'a11y@example.test', 'published_at' => now()]);

    auth()->logout();
    $this->get("/accessibility-statement/{$statement->public_token}", ['Accept-Language' => 'fr'])
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('locale', 'de'));

    $html = $this->actingAs($site->user)->get("/sites/{$site->id}/report?format=html")->assertOk()->getContent();
    expect($html)->toContain('<html lang="de">')->toContain('Barrierefreiheit');

    $english = $this->actingAs($site->user)->get("/sites/{$site->id}/report?format=html&lang=en")->getContent();
    expect($english)->toContain('<html lang="en">');
});
