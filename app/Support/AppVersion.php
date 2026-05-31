<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

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
    private const CACHE_TTL = 60;          // seconds — local git is fast, but no need to shell out per request

    private const GIT_TIMEOUT = 2.0;       // seconds — a wedged git lock can hang forever otherwise

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
        $tag = (string) config('stockroom.version.tag');
        $sha = (string) config('stockroom.version.commit');

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
     * Run a short-lived git command with a hard timeout. A wedged git
     * lock or a missing binary returns an empty string rather than
     * propagating — the caller hides the chip on empty.
     *
     * @param  list<string>  $cmd
     */
    private static function run(array $cmd): string
    {
        $process = new Process($cmd);
        $process->setTimeout(self::GIT_TIMEOUT);

        try {
            $process->run();
        } catch (ProcessTimedOutException|ProcessFailedException) {
            return '';
        }

        return $process->isSuccessful() ? trim($process->getOutput()) : '';
    }
}
