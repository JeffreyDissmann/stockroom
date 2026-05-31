<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Resolves the running app's version + commit for display on the login
 * page and in build-info panels.
 *
 * Production deploys flow through Docker --build-arg → ENV → config
 * (see Dockerfile + config/stockroom.php); reading from config rather
 * than env() means it survives `config:cache`. Dev environments without
 * those env vars fall back to a cached `git describe` shell-out. Both
 * fields return `null` when we can't resolve them — callers should
 * hide the chip rather than render "unknown".
 */
class AppVersion
{
    private const CACHE_TTL = 60; // seconds — local git is fast, but no need to shell out per request

    /**
     * @return array{tag: string|null, sha: string|null}
     */
    public static function current(): array
    {
        // Read from config rather than env() so this survives
        // `php artisan config:cache` in production — env() returns null
        // for keys not surfaced through a config file once config is
        // cached. The Dockerfile passes APP_VERSION / APP_COMMIT as
        // --build-arg, the entrypoint runs config:cache, and the chip
        // lights up.
        $tag = (string) (config('stockroom.version.tag') ?? '');
        $sha = (string) (config('stockroom.version.commit') ?? '');

        if ($tag !== '' || $sha !== '') {
            return [
                'tag' => $tag !== '' ? $tag : null,
                'sha' => $sha !== '' ? substr($sha, 0, 7) : null,
            ];
        }

        // Dev-only fallback: derive from the working .git. Cached briefly
        // so repeated Inertia requests don't fork `git` over and over.
        // Skipped in production because the runtime image has neither a
        // git binary nor a .git directory.
        return Cache::remember('app_version', self::CACHE_TTL, fn () => self::deriveFromGit());
    }

    /**
     * @return array{tag: string|null, sha: string|null}
     */
    private static function deriveFromGit(): array
    {
        $base = base_path();
        if (! is_dir($base.'/.git')) {
            return ['tag' => null, 'sha' => null];
        }

        $tag = self::run(['git', '-C', $base, 'describe', '--tags', '--abbrev=0']);
        $sha = self::run(['git', '-C', $base, 'rev-parse', '--short=7', 'HEAD']);

        return [
            'tag' => $tag === '' ? null : $tag,
            'sha' => $sha === '' ? null : $sha,
        ];
    }

    /**
     * @param  list<string>  $cmd
     */
    private static function run(array $cmd): string
    {
        // proc_open + trimmed stdout. Suppresses stderr so a missing tag
        // ("fatal: No names found") doesn't pollute the log.
        $proc = proc_open(
            $cmd,
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $cmd[2] ?? null, // -C path is at index 2 for our calls
        );

        if (! is_resource($proc)) {
            return '';
        }

        $out = trim((string) stream_get_contents($pipes[1]));
        foreach ($pipes as $pipe) {
            fclose($pipe);
        }
        proc_close($proc);

        return $out;
    }
}
