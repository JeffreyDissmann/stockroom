<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ImportBackupRequest;
use App\Services\Backup\BackupExporter;
use App\Services\Backup\BackupImporter;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupController extends Controller
{
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
