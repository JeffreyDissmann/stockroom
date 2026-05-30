<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\ProcessPaperlessDocumentJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Receives Paperless-ngx workflow webhook POSTs. The Paperless workflow
 * configured by `paperless:install` triggers when a document gains the
 * trigger tag, then POSTs here with the doc id as a form param.
 *
 * The route is wrapped in EnsurePaperlessEnabled (404 when integration is
 * disabled) and VerifyPaperlessSignature (401 / 503 on auth failure), so
 * this controller assumes a valid, authorised request.
 */
class PaperlessWebhookController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            // Paperless sends the doc id as a string via {{doc_id}}
            // template substitution; we coerce to int after validation.
            'document_id' => ['required', 'integer', 'min:1'],
        ]);

        $documentId = (int) $validated['document_id'];

        ProcessPaperlessDocumentJob::dispatch($documentId);

        // 202 Accepted is the right semantic — the work is queued, not done.
        // Paperless's workflow webhook ignores response bodies but a small
        // payload helps anyone curl-ing the endpoint for diagnostics.
        return response()->json(
            ['accepted' => true, 'document_id' => $documentId],
            202,
        );
    }
}
