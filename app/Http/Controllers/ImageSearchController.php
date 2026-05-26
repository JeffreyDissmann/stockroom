<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureImageSearchEnabled;
use App\Http\Requests\Item\AttachImagesFromSearchRequest;
use App\Models\Item;
use App\Services\Brave\BraveException;
use App\Services\Brave\BraveImageSearchClient;
use App\Services\Brave\RemoteImageDownloader;
use App\Services\ItemImageProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Validation\ValidationException;
use Throwable;

#[Middleware(EnsureImageSearchEnabled::class)]
class ImageSearchController extends Controller
{
    public function __construct(
        private readonly ItemImageProcessor $processor,
        private readonly RemoteImageDownloader $downloader,
    ) {}

    /**
     * Search external images for an item. When no query is given, a default is
     * distilled from the item and echoed back so the UI can prefill the box.
     */
    public function search(Request $request, Item $item): JsonResponse
    {
        $validated = $request->validate(['q' => ['nullable', 'string', 'max:200']]);
        $query = trim((string) ($validated['q'] ?? '')) ?: $item->defaultImageSearchQuery();

        if ($query === '') {
            return response()->json(['query' => '', 'results' => []]);
        }

        try {
            $results = BraveImageSearchClient::default()->search($query);
        } catch (BraveException) {
            abort(502, 'Image search is temporarily unavailable.');
        }

        return response()->json(['query' => $query, 'results' => $results]);
    }

    /**
     * Download the chosen images and attach them to the item via the existing
     * image pipeline. A bad URL is skipped, not fatal — but if none succeed the
     * user gets an error back.
     */
    public function attach(AttachImagesFromSearchRequest $request, Item $item): RedirectResponse
    {
        $stored = 0;

        foreach ($request->validated('urls') as $url) {
            try {
                $this->storeFromUrl($item, (string) $url);
                $stored++;
            } catch (Throwable) {
                // Skip an individual bad/unsafe/undecodable URL.
            }
        }

        if ($stored === 0) {
            throw ValidationException::withMessages([
                'urls' => 'None of the selected images could be downloaded.',
            ]);
        }

        $item->logImagesAdded($stored);

        return back();
    }

    private function storeFromUrl(Item $item, string $url): void
    {
        $image = $this->downloader->download($url);
        $tempPath = (string) tempnam(sys_get_temp_dir(), 'brave-img-');

        try {
            file_put_contents($tempPath, $image->contents);
            $this->processor->store($item, new UploadedFile($tempPath, $image->filename, $image->mime, null, true));
        } finally {
            @unlink($tempPath);
        }
    }
}
