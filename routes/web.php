<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $docsUrl = config('scramble.doc_path', '/docs/api');
    if (!Route::has('scramble.docs.index') && $docsUrl === '/docs') {
        $docsUrl = '/docs/api';
    } elseif (Route::has('scramble.docs.index')) {
        try {
            $docsUrl = route('scramble.docs.index', [], false);
        } catch (\Exception $e) {
            $docsUrl = config('scramble.doc_path', '/docs/api');
        }
    }

    return response()->json([
        'message' => 'Welcome to the GregTech New Horizons API!',
        'documentation' => url($docsUrl),
        'version' => config('app.api_version', '1.0.0'),
    ]);
})->name('home');
