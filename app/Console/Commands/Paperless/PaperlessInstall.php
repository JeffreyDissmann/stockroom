<?php

declare(strict_types=1);

namespace App\Console\Commands\Paperless;

use App\Services\Paperless\PaperlessClient;
use App\Services\Paperless\PaperlessException;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * One-shot setup for the Paperless-ngx integration (#7). Idempotent — safe
 * to run repeatedly:
 *
 *   - Creates the trigger tag, linked tag and link-back custom field in
 *     Paperless if they don't exist yet. Existing items are left alone.
 *   - Generates PAPERLESS_WEBHOOK_SECRET in .env if it's blank, so the
 *     webhook signature check has something to verify against.
 *
 * The names come from config — running with a configured custom set of
 * names works without flags.
 */
class PaperlessInstall extends Command
{
    protected $signature = 'paperless:install
        {--force-secret : Regenerate PAPERLESS_WEBHOOK_SECRET even if one is already set}';

    protected $description = 'Create the required Paperless tags + custom field, and seed the webhook secret in .env';

    public function handle(): int
    {
        $client = PaperlessClient::fromConfig();
        if ($client === null) {
            $this->error('Paperless is not configured. Set PAPERLESS_URL and PAPERLESS_TOKEN in .env first.');

            return self::FAILURE;
        }

        $triggerTag = (string) config('paperless.trigger_tag');
        $linkedTag = (string) config('paperless.linked_tag');
        $customField = (string) config('paperless.link_custom_field');

        // The webhook secret must exist BEFORE the workflow gets created in
        // Paperless: the workflow embeds the secret as a header so each
        // intake POST authenticates against it. Without this, the workflow
        // would carry an empty secret and every webhook would 401.
        $secretAction = $this->seedWebhookSecret($this->option('force-secret'));

        $appUrl = rtrim((string) config('app.url'), '/');
        if ($appUrl === '') {
            $this->error('APP_URL is not set. Paperless needs a reachable URL to call back to.');

            return self::FAILURE;
        }
        $webhookUrl = $appUrl.'/webhooks/paperless/document';
        $secret = (string) config('paperless.webhook_secret');
        $workflowName = 'Stockroom intake';

        try {
            // Tag colors line up with the Stockroom mono design tokens:
            // the linked tag uses the near-black accent (#0a0a0a, same as
            // --accent in light mode), the trigger tag uses a muted gray
            // (#a1a1a1, ≈ --fg-subtle) to read as "queued / in progress".
            // Only applied on first creation — manual tweaks in Paperless
            // survive a re-install.
            [$triggerTagId, $triggerCreated] = $client->ensureTag($triggerTag, '#a1a1a1');
            [, $linkedCreated] = $client->ensureTag($linkedTag, '#0a0a0a');
            // 'url' so Paperless renders it as a clickable link straight to
            // Stockroom's search-filtered view of the items extracted from
            // this doc.
            [, $fieldCreated] = $client->ensureCustomField($customField, 'url');
            [, $workflowStatus] = $client->ensureWorkflow($workflowName, $triggerTagId, $webhookUrl, $secret);
        } catch (PaperlessException $e) {
            $this->error("Paperless API error: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->components->twoColumnDetail("Tag <fg=cyan>{$triggerTag}</>", $triggerCreated ? '<fg=green>created</>' : 'already exists');
        $this->components->twoColumnDetail("Tag <fg=cyan>{$linkedTag}</>", $linkedCreated ? '<fg=green>created</>' : 'already exists');
        $this->components->twoColumnDetail("Custom field <fg=cyan>{$customField}</>", $fieldCreated ? '<fg=green>created</>' : 'already exists');
        $this->components->twoColumnDetail('Webhook secret', match ($secretAction) {
            'generated' => '<fg=green>generated and written to .env</>',
            'regenerated' => '<fg=yellow>regenerated and written to .env</>',
            'kept' => 'already set, kept as-is',
            'no_env' => '<fg=yellow>no .env file found, skipped</>',
        });
        $this->components->twoColumnDetail("Workflow <fg=cyan>{$workflowName}</>", match ($workflowStatus) {
            'created' => '<fg=green>created</>',
            'updated' => '<fg=yellow>updated (URL or secret had drifted)</>',
            'unchanged' => 'already exists',
        });
        $this->components->twoColumnDetail('  → webhook url', "<fg=gray>{$webhookUrl}</>");

        $this->newLine();
        $this->info('Paperless integration is ready. Tag a document with "'.$triggerTag.'" in Paperless to test the flow.');

        return self::SUCCESS;
    }

    /**
     * Ensure PAPERLESS_WEBHOOK_SECRET is set in .env. Generates a random
     * 32-byte hex string when missing (or when --force-secret is passed).
     *
     * Returns one of: 'generated' | 'regenerated' | 'kept' | 'no_env'.
     */
    private function seedWebhookSecret(bool $force): string
    {
        $envPath = base_path('.env');
        if (! is_file($envPath)) {
            return 'no_env';
        }

        $current = (string) config('paperless.webhook_secret');
        if ($current !== '' && ! $force) {
            return 'kept';
        }

        $secret = bin2hex(random_bytes(32));
        $env = (string) file_get_contents($envPath);

        if (preg_match('/^PAPERLESS_WEBHOOK_SECRET=.*$/m', $env)) {
            $env = preg_replace('/^PAPERLESS_WEBHOOK_SECRET=.*$/m', "PAPERLESS_WEBHOOK_SECRET={$secret}", $env);
        } else {
            // No line at all — append. Newline before so we don't run into
            // the previous line if the file didn't end with one.
            $env = rtrim($env, "\n")."\n\nPAPERLESS_WEBHOOK_SECRET={$secret}\n";
        }

        // Best-effort atomic write: tmp then rename, so a partial write can't
        // leave .env half-rewritten. atomic = same filesystem so no rename
        // surprises.
        $tmp = $envPath.'.'.Str::random(8).'.tmp';
        file_put_contents($tmp, $env);
        rename($tmp, $envPath);

        // Live config gets the new secret too, in case anything later in the
        // process reads it — otherwise it'd see the stale (blank) value
        // until the next boot.
        config(['paperless.webhook_secret' => $secret]);

        return $current === '' ? 'generated' : 'regenerated';
    }
}
