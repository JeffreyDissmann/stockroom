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

class ImportController extends Controller
{
    /**
     * Kicks off a background HomeBox import. The form lives on the Backup
     * & Import screen (BackupController::index renders it via the
     * household/Backup Inertia page), so this controller is action-only —
     * there's no GET counterpart.
     */
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

        // Stamp 'discovering' as the initial state so the page banner appears
        // immediately on the redirect-back render — without it the user sees
        // the form reset to empty and assumes nothing happened. The job will
        // re-stamp this on handle() too as a belt-and-braces in case the cache
        // entry got evicted before the worker picked the job up.
        Cache::put(ImportFromHomeboxJob::STATUS_KEY, ['state' => 'discovering'], now()->addHour());

        ImportFromHomeboxJob::dispatch($client->baseUrl(), $client->token());

        return back();
    }
}
