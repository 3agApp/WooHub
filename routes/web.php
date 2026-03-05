<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::redirect('/', '/dashboard')->name('home');

if (app()->environment('local')) {
    Route::get('/flash-test/{type}', function (string $type) {
        $toast = match ($type) {
            'success' => [
                'message' => 'Success toast triggered.',
                'description' => 'This is a local flash test for a success toast.',
            ],
            'error' => [
                'message' => 'Error toast triggered.',
                'description' => 'This is a local flash test for an error toast.',
            ],
            'warning' => [
                'message' => 'Warning toast triggered.',
            ],
            'info' => [
                'message' => 'Info toast triggered.',
            ],
            default => [
                'message' => 'Toast triggered.',
            ],
        };

        Inertia::flash('toast', [
            'type' => $type,
            'message' => $toast['message'],
            'description' => $toast['description'] ?? null,
        ]);

        return back();
    })->whereIn('type', ['success', 'error', 'warning', 'info'])->name('flash.test');
}
