<?php

declare(strict_types=1);

namespace App\Http\Controllers\Household;

use App\Http\Controllers\Controller;
use App\Http\Requests\Household\ImportBackupRequest;
use App\Jobs\ImportFromHomeboxJob;
use App\Services\Backup\BackupExporter;
use App\Services\Backup\BackupImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupController extends Controller
{
    /**
     * The combined Backup & Import screen. The page renders the Stockroom
     * backup archive controls, the HomeBox import form, and the wipe
     * "danger zone" — the in-flight HomeBox progress (if any) is forwarded
     * here too so the polling UI in HomeboxImport.vue can pick it up
     * without a separate page or endpoint.
     */
    public function index(): Response
    {
        return Inertia::render('household/Backup', [
            'status' => Cache::get(ImportFromHomeboxJob::STATUS_KEY),
        ]);
    }

    public function export(BackupExporter $exporter): BinaryFileResponse
    {
        $path = $exporter->export();
        $name = 'stockroom-backup-'.now()->format('Y-m-d-His').'.zip';

        return response()
            ->download($path, $name, ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend();
    }

    public function import(ImportBackupRequest $request, BackupImporter $importer): RedirectResponse
    {
        $counts = $importer->import($request->file('file')->getRealPath());

        return back()->with('backup', $counts);
    }
}
