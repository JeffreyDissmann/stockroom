<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Ai\Agents\ItemPhotoAnalyzer;
use App\Http\Middleware\EnsureAiEnabled;
use App\Http\Requests\Item\AnalyzeItemPhotoRequest;
use App\Services\ItemImageProcessor;
use ArrayAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Laravel\Ai\Files\Base64Image;
use Laravel\Ai\Files\Image;
use Throwable;

/**
 * Runs a single uploaded photo through the vision agent and returns the
 * extracted catalogue fields as JSON for the create form to pre-fill. The
 * photo is analysed only — it is not stored here (the user adds images via
 * the normal image manager).
 */
#[Middleware(EnsureAiEnabled::class)]
class ItemPhotoAnalysisController extends Controller
{
    /**
     * Longest edge (px) the photo is scaled down to before it is sent to the
     * model. Large enough to keep model/serial numbers legible, small enough to
     * avoid shipping a multi-megabyte original the model would only downsample.
     */
    private const MAX_EDGE = 1280;

    private const QUALITY = 80;

    public function __construct(
        private readonly ItemImageProcessor $images,
    ) {}

    public function __invoke(AnalyzeItemPhotoRequest $request): JsonResponse
    {
        // Propose name/description in the user's language (set on the request by
        // SetLocale); identifiers are kept verbatim by the agent regardless.
        $language = config('app.supported_locales.'.app()->getLocale().'.ai', 'English');

        try {
            $response = (new ItemPhotoAnalyzer($language))->prompt(
                'Catalogue the main item shown in this photo.',
                attachments: [$this->downscaledImage($request->file('photo'))],
                model: config('ai.vision_model'),
                timeout: 120,
            );
        } catch (Throwable) {
            abort(502, 'The photo could not be analysed. Please try again or fill the form manually.');
        }

        abort_unless($response instanceof ArrayAccess, 502, 'Unexpected response from the vision model.');

        // Return a stable, known-shape payload: only our schema keys, trimmed,
        // with blanks normalised to null so the form fills only confident values.
        $fields = [];

        foreach (['name', 'manufacturer', 'model_number', 'serial_number', 'description'] as $key) {
            $value = $response[$key] ?? null;
            $fields[$key] = is_string($value) && trim($value) !== '' ? trim($value) : null;
        }

        return response()->json(['fields' => $fields]);
    }

    /**
     * Build a downscaled JPEG attachment from the upload so the model receives a
     * lightweight image instead of the full-resolution original.
     */
    private function downscaledImage(UploadedFile $photo): Base64Image
    {
        $jpeg = $this->images->downscaleToJpeg($photo, self::MAX_EDGE, self::QUALITY);

        return Image::fromBase64(base64_encode($jpeg), 'image/jpeg');
    }
}
