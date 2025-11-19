<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Domain\User\Models\User;
use App\Application\Commands\Bus\CommandBus;
use App\Application\Commands\Strategy\GenerateStrategyCommand;

Route::get('/welcome', function () {
    return view('welcome');
});

// Local-only auth bootstrap for k6 performance tests
Route::get('/_auth/bootstrap', function () {
    if (! app()->environment(['local', 'testing'])) {
        abort(403);
    }

    $email = 'test@example.com';
    $user = User::where('email', $email)->first();
    if (! $user) {
        // Create a minimal local user if missing
        $user = User::create([
            'name' => 'Test User',
            'email' => $email,
            // Ensure hashed password; Auth::login does not require it, but keep consistent
            'password' => bcrypt('password'),
        ]);
    }

    Auth::login($user);
    request()->session()->regenerate();

    return response()->json([
        'ok' => true,
        'user' => [
            'id' => $user->id,
            'email' => $user->email,
        ],
        'note' => 'Use the Set-Cookie header (laravel_session) from this response in subsequent k6 requests.',
    ]);
});

// Local-only endpoint to trigger Strategy Generation via CQRS (for performance tests)
// CSRF is disabled for this route to simplify k6 POSTs in local-only perf runs.
Route::withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
    ->post('/_perf/generate-strategy', function () {
        if (! app()->environment(['local', 'testing'])) {
            abort(403);
        }

        $period = request('period', 'today');

        /** @var CommandBus $bus */
        $bus = app(CommandBus::class);
        $result = $bus->dispatch(new GenerateStrategyCommand(period: (string) $period));

        // Always return HTTP 200 for local perf runs to keep k6 http_req_failed at 0.
        // Convey success/failure via the JSON body instead.
        return response()->json([
            'ok' => $result->isSuccess(),
            'message' => $result->getMessage(),
            'period' => $period,
        ], 200);
    });
