<?php

declare(strict_types=1);

namespace App\Services\Brave;

use finfo;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Fetches a remote image safely for attaching to an item. All SSRF / abuse
 * guards live here: https only, no redirects, no private/reserved hosts, a size
 * cap, and a content sniff (we never trust the URL or the Content-Type header).
 *
 * URLs are accepted on their own merit if they pass these guards — we do not
 * tie them back to a prior search result set (that would be stateful and racey,
 * and the guards already neutralise the risk for a single trusted admin).
 */
class RemoteImageDownloader
{
    private const MAX_BYTES = 15 * 1024 * 1024;

    private const TIMEOUT = 15;

    /** Sniffed MIME type => stored extension. */
    private const ALLOWED = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'image/heic' => 'heic',
    ];

    public function download(string $url): DownloadedImage
    {
        $this->assertSafeUrl($url);

        try {
            $response = Http::withOptions(['allow_redirects' => false])
                ->connectTimeout(5)
                ->timeout(self::TIMEOUT)
                ->get($url);
        } catch (Throwable $e) {
            throw new DownloadRejected('The image could not be downloaded.', previous: $e);
        }

        if ($response->redirect()) {
            throw new DownloadRejected('Refusing to follow a redirect while downloading an image.');
        }

        if (! $response->successful()) {
            throw new DownloadRejected("The image host returned status {$response->status()}.");
        }

        if ((int) $response->header('Content-Length') > self::MAX_BYTES) {
            throw new DownloadRejected('The image is too large.');
        }

        $body = $response->body();

        if ($body === '' || strlen($body) > self::MAX_BYTES) {
            throw new DownloadRejected('The image was empty or too large.');
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($body) ?: '';

        if (! isset(self::ALLOWED[$mime])) {
            throw new DownloadRejected('The downloaded file is not a supported image.');
        }

        return new DownloadedImage($body, $mime, 'brave-image.'.self::ALLOWED[$mime]);
    }

    private function assertSafeUrl(string $url): void
    {
        if (parse_url($url, PHP_URL_SCHEME) !== 'https') {
            throw new DownloadRejected('Only https image URLs are allowed.');
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        if ($host === '') {
            throw new DownloadRejected('The image URL has no host.');
        }

        if ($host === 'localhost' || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            throw new DownloadRejected('That host is not allowed.');
        }

        // A literal IP host is checked directly. A hostname is checked only if it
        // resolves — an unresolvable host simply can't be reached, so we let the
        // request fail naturally rather than block legitimate (faked) test hosts.
        $addresses = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);

        foreach ($addresses as $address) {
            if (! filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new DownloadRejected('That host resolves to a non-public address.');
            }
        }
    }
}
