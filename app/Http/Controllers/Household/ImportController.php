<?php

declare(strict_types=1);

namespace App\Http\Controllers\Household;

use App\Http\Controllers\Controller;
use App\Http\Requests\Household\StartHomeboxImportRequest;
use App\Jobs\ImportFromHomeboxJob;
use App\Services\Homebox\HomeboxClient;
use App\Services\Homebox\HomeboxException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ImportController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('household/Import', [
            'status' => Cache::get(ImportFromHomeboxJob::STATUS_KEY),
        ]);
    }

    public function start(StartHomeboxImportRequest $request): RedirectResponse
    {
        // Exchange credentials for a token now; only the token is handed to the
        // job — the password is never stored.
        try {
            $client = HomeboxClient::login(
                $request->string('url')->value(),
                $request->string('username')->value(),
                $request->string('password')->value(),
            );
        } catch (HomeboxException $e) {
            throw ValidationException::withMessages(['connection' => $e->getMessage()]);
        }

        Cache::put(ImportFromHomeboxJob::STATUS_KEY, ['state' => 'running', 'done' => 0, 'total' => 0], now()->addHour());

        ImportFromHomeboxJob::dispatch($client->baseUrl(), $client->token());

        return back();
    }
}
