<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Vite;

uses(RefreshDatabase::class);

it('links the manifest and the theme colour from every page', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('<link rel="manifest" href="/manifest.json">', false)
        ->assertSee('<meta name="theme-color" content="#16A34A">', false)
        ->assertSee('<meta name="mobile-web-app-capable" content="yes">', false)
        ->assertSee('<meta name="apple-mobile-web-app-capable" content="yes">', false)
        ->assertSee('<meta name="apple-mobile-web-app-title" content="Pitch">', false);
});

it('registers the service worker under the hash of the current build', function () {
    $hash = Vite::manifestHash();

    expect($hash)->toBeString();

    $this->get(route('home'))
        ->assertOk()
        ->assertSee("navigator.serviceWorker.register('/sw.js?v={$hash}')", false);
});

it('registers no service worker while Vite is running hot', function () {
    $hot = tempnam(sys_get_temp_dir(), 'hot');
    file_put_contents($hot, 'http://localhost:5173');
    Vite::useHotFile($hot);

    try {
        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('serviceWorker', false);
    } finally {
        unlink($hot);
    }
});

it('ships an installable manifest in the app colours', function () {
    $manifest = json_decode((string) file_get_contents(public_path('manifest.json')), true, flags: JSON_THROW_ON_ERROR);

    expect($manifest)->toMatchArray([
        'name' => 'Pitch',
        'short_name' => 'Pitch',
        'id' => '/',
        'start_url' => '/',
        'scope' => '/',
        'display' => 'standalone',
        'theme_color' => '#16A34A',
        'background_color' => '#16A34A',
    ]);
});

it('ships every manifest icon as a png at its declared size', function () {
    $manifest = json_decode((string) file_get_contents(public_path('manifest.json')), true, flags: JSON_THROW_ON_ERROR);
    $icons = collect($manifest['icons']);

    expect($icons->where('purpose', 'any')->pluck('sizes')->all())->toContain('192x192', '512x512')
        ->and($icons->where('purpose', 'maskable')->pluck('sizes')->all())->toContain('192x192', '512x512');

    foreach ($icons as $icon) {
        $size = getimagesize(public_path(ltrim($icon['src'], '/')));

        expect($size)->not->toBeFalse()
            ->and("{$size[0]}x{$size[1]}")->toBe($icon['sizes'])
            ->and($size['mime'])->toBe('image/png')
            ->and($icon['type'])->toBe('image/png');
    }
});
