<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Ai\Agents\InventoryAssistant;
use App\Enums\ItemType;
use App\Models\Item;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * One-off benchmark: runs a fixed set of assistant scenarios against several
 * Ollama models, one model at a time (Ollama only hosts one), measuring
 * response latency and auto-scoring answer/behaviour quality. Every scenario
 * runs inside a rolled-back transaction with search syncing disabled, so write
 * scenarios can never mutate real inventory or the search index.
 *
 * Not part of the test suite — it hits the live model server and is slow.
 */
class BenchmarkAssistant extends Command
{
    protected $signature = 'ai:benchmark {--models= : Comma-separated Ollama model tags} {--timeout=180}';

    protected $description = 'Benchmark the inventory assistant across Ollama models (speed + quality)';

    public function handle(): int
    {
        $models = array_filter(array_map('trim', explode(',', (string) $this->option('models'))));

        if ($models === []) {
            $this->error('Pass --models=tag1,tag2,...');

            return self::FAILURE;
        }

        $user = User::query()->orderBy('id')->firstOrFail();
        $timeout = (int) $this->option('timeout');
        $truth = $this->gatherGroundTruth();
        $scenarios = $this->scenarios($truth);
        $outFile = storage_path('app/benchmark/results-'.now()->format('Ymd-His').'.json');
        @mkdir(dirname($outFile), 0777, true);

        $all = [];

        foreach ($models as $model) {
            $this->line("\n=== {$model} ===");
            $this->warmUp($model, $user, $timeout);

            $modelResult = ['model' => $model, 'scenarios' => [], 'total_secs' => 0.0, 'turns' => 0, 'checks_passed' => 0, 'checks_total' => 0];

            foreach ($scenarios as $scenario) {
                $run = $this->runScenario($model, $user, $scenario, $timeout);

                $passed = count(array_filter($run['checks']));
                $total = count($run['checks']);

                $modelResult['scenarios'][$scenario['key']] = $run;
                $modelResult['total_secs'] += $run['secs'];
                $modelResult['turns'] += count($scenario['prompts']);
                $modelResult['checks_passed'] += $passed;
                $modelResult['checks_total'] += $total;

                $this->line(sprintf('  %-14s %5.1fs  %d/%d  %s', $scenario['key'], $run['secs'], $passed, $total, $this->checkSummary($run['checks'])));
            }

            $modelResult['avg_secs_per_turn'] = $modelResult['turns'] > 0 ? round($modelResult['total_secs'] / $modelResult['turns'], 2) : 0;
            $modelResult['quality_pct'] = $modelResult['checks_total'] > 0 ? round(100 * $modelResult['checks_passed'] / $modelResult['checks_total']) : 0;

            $all[] = $modelResult;
            file_put_contents($outFile, json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            $this->info(sprintf('  => %s: quality %d%%, avg %.2fs/turn, total %.1fs', $model, $modelResult['quality_pct'], $modelResult['avg_secs_per_turn'], $modelResult['total_secs']));
        }

        $this->renderTable($all);
        $this->info("\nFull results: {$outFile}");

        return self::SUCCESS;
    }

    /**
     * Load the model (and surface tool-support errors) without timing it.
     */
    private function warmUp(string $model, User $user, int $timeout): void
    {
        try {
            $agent = (new InventoryAssistant)->forUser($user); // @phpstan-ignore-line
            $start = microtime(true);
            $agent->prompt('Reply with the single word OK.', model: $model, timeout: $timeout);
            $this->line(sprintf('  (warm-up/load %.1fs)', microtime(true) - $start));
        } catch (Throwable $e) {
            $this->warn('  warm-up error: '.$e->getMessage());
        }
    }

    /**
     * @param  array{key:string,prompts:array<int,string>,checks:callable}  $scenario
     * @return array{secs:float,turns:array<int,mixed>,checks:array<string,bool>}
     */
    private function runScenario(string $model, User $user, array $scenario, int $timeout): array
    {
        $turns = [];
        $secs = 0.0;

        DB::beginTransaction();

        try {
            Item::withoutSyncingToSearch(function () use (&$turns, &$secs, $scenario, $model, $user, $timeout): void {
                $conversationId = null;

                foreach ($scenario['prompts'] as $i => $prompt) {
                    $agent = (new InventoryAssistant)->forUser($user);
                    if ($i > 0 && $conversationId !== null) {
                        $agent = (new InventoryAssistant)->forUser($user)->continue($conversationId, $user);
                    }

                    $start = microtime(true);
                    try {
                        $response = $agent->prompt($prompt, model: $model, timeout: $timeout);
                        $elapsed = microtime(true) - $start;
                        $conversationId = $agent->currentConversation();
                        $turns[] = ['text' => (string) $response->text, 'tools' => $this->toolNames($response), 'secs' => round($elapsed, 2)];
                    } catch (Throwable $e) {
                        $turns[] = ['text' => '__ERROR__ '.$e->getMessage(), 'tools' => [], 'secs' => round(microtime(true) - $start, 2)];
                    }
                    $secs += end($turns)['secs'];
                }
            });

            $checks = ($scenario['checks'])($turns);
        } catch (Throwable $e) {
            $checks = ['error' => false];
            $turns[] = ['text' => '__SCENARIO_ERROR__ '.$e->getMessage(), 'tools' => [], 'secs' => 0];
        } finally {
            DB::rollBack();
        }

        return ['secs' => round($secs, 2), 'turns' => $turns, 'checks' => $checks];
    }

    /**
     * Scenarios + auto-scoring driven by ground truth gathered from the live DB,
     * so the benchmark stays correct as the inventory grows or names change.
     *
     * @param  array<string, mixed>  $truth
     * @return array<int, array{key:string,prompts:array<int,string>,checks:callable}>
     */
    private function scenarios(array $truth): array
    {
        $locate = $truth['locate'];
        $tagged = $truth['tag_value'];
        $move = $truth['move'];

        return [
            [
                'key' => 'locate',
                'prompts' => ["Where is the {$locate['name']}? Give its exact location."],
                'checks' => fn (array $t): array => [
                    'location' => str_contains(mb_strtolower($t[0]['text']), mb_strtolower($locate['location_keyword'])),
                    'item_link' => (bool) preg_match("#/items/{$locate['id']}\b#", $t[0]['text']),
                    'used_tool' => $t[0]['tools'] !== [],
                ],
            ],
            [
                'key' => 'count_items',
                'prompts' => ['How many items do I own — actual possessions, not rooms or containers?'],
                'checks' => fn (array $t): array => [
                    'correct_count' => $this->hasNumber($t[0]['text'], $truth['items_count']),
                ],
            ],
            [
                'key' => 'total_value',
                'prompts' => ['What is the total value of everything I currently own?'],
                'checks' => fn (array $t): array => [
                    'value' => $this->hasMoney($t[0]['text'], (string) $truth['total_value_int']),
                    'currency' => stripos($t[0]['text'], $truth['currency']) !== false || str_contains($t[0]['text'], '€'),
                ],
            ],
            [
                'key' => 'tag_value',
                'prompts' => ["How much is everything tagged {$tagged['name']} worth in total?"],
                'checks' => fn (array $t): array => [
                    'value' => $this->hasMoney($t[0]['text'], (string) $tagged['value_int']),
                ],
            ],
            [
                'key' => 'memory',
                'prompts' => ["Where is the {$locate['name']}?", 'And what is its manufacturer?'],
                'checks' => fn (array $t): array => [
                    'remembered' => isset($t[1]) && stripos($t[1]['text'], $locate['manufacturer_keyword']) !== false,
                    'no_reask' => isset($t[1]) && ! preg_match('/which item|what item|please (provide|specify|clarify)|which one/i', $t[1]['text']),
                ],
            ],
            [
                'key' => 'confirm_write',
                'prompts' => ['Create a new item called Benchmark Widget of type item.'],
                'checks' => fn (array $t): array => [
                    'did_not_create' => ! $this->calledTool($t[0]['tools'], 'create'),
                    'asked_confirm' => (bool) preg_match('/confirm|shall i|should i|proceed|do you want|bestätig|möchtest|correct\?/i', $t[0]['text']),
                ],
            ],
            [
                'key' => 'move_by_name',
                'prompts' => ["Move the {$move['item_name']} into {$move['room_name']}.", 'Yes, please do it now.'],
                'checks' => fn (array $t): array => [
                    't1_confirms_no_move' => ! $this->calledTool($t[0]['tools'], 'move'),
                    't2_moved_correctly' => isset($t[1]) && $this->calledTool($t[1]['tools'], 'move') && Item::find($move['item_id'])?->parent_id === $move['room_id'],
                ],
            ],
        ];
    }

    /**
     * Pluck the live numbers and names the scenarios assert against, so the
     * benchmark doesn't carry stale magic numbers when the inventory shifts.
     *
     * @return array<string, mixed>
     */
    private function gatherGroundTruth(): array
    {
        $items = Item::where('type', ItemType::Item);

        $itemsCount = (clone $items)->count();
        $totalValue = (int) round((float) (clone $items)->whereNull('sold_date')->sum('purchase_price'));

        /** @var Item $locate */
        $locate = (clone $items)
            ->whereNotNull('manufacturer')
            ->whereNotNull('parent_id')
            ->orderBy('id')
            ->firstOrFail();
        $locationPath = $locate->locationPath();

        /** @var Tag $topTag */
        $topTag = Tag::query()
            ->withSum(['items as match_value' => fn ($q) => $q->where('type', ItemType::Item)->whereNull('sold_date')], 'purchase_price')
            ->get()
            ->filter(fn (Tag $tag): bool => (float) ($tag->match_value ?? 0) > 0)
            ->sortByDesc('match_value')
            ->firstOrFail();

        /** @var Item $room */
        $room = Item::where('type', ItemType::Room)->orderBy('id')->firstOrFail();

        return [
            'items_count' => $itemsCount,
            'total_value_int' => $totalValue,
            'currency' => (string) config('stockroom.currency.code', 'USD'),
            'locate' => [
                'id' => $locate->id,
                'name' => $locate->name,
                'manufacturer_keyword' => mb_strtolower(explode(' ', trim((string) $locate->manufacturer))[0]),
                'location_keyword' => mb_strtolower(($parts = explode(' / ', $locationPath))[count($parts) - 1] ?: 'top level'),
            ],
            'tag_value' => [
                'name' => $topTag->name,
                'value_int' => (int) round((float) $topTag->match_value),
            ],
            'move' => [
                'item_id' => $locate->id,
                'item_name' => $locate->name,
                'room_id' => $room->id,
                'room_name' => $room->name,
            ],
        ];
    }

    private function toolNames(object $response): array
    {
        return collect($response->toolCalls ?? [])
            ->map(fn ($c) => is_object($c) ? ($c->name ?? '') : ($c['name'] ?? ''))
            ->filter()
            ->values()
            ->all();
    }

    private function calledTool(array $tools, string $needle): bool
    {
        foreach ($tools as $tool) {
            if (str_contains(mb_strtolower((string) $tool), $needle)) {
                return true;
            }
        }

        return false;
    }

    private function hasNumber(string $text, int $n): bool
    {
        return (bool) preg_match('/(?<![\d.,])'.preg_quote((string) $n, '/').'(?![\d.,])/', $text);
    }

    /**
     * Match a money figure regardless of thousands separators (25455 → also
     * matches "25,455", "25.455", "25 455").
     */
    private function hasMoney(string $text, string $digits): bool
    {
        $normalised = preg_replace('/(\d)[.,\s](\d)/', '$1$2', $text) ?? $text;

        return str_contains($normalised, $digits);
    }

    private function checkSummary(array $checks): string
    {
        return collect($checks)->map(fn (bool $ok, string $k): string => ($ok ? '✓' : '✗').$k)->implode(' ');
    }

    /**
     * @param  array<int, array<string, mixed>>  $all
     */
    private function renderTable(array $all): void
    {
        $rows = collect($all)
            ->sortByDesc('quality_pct')
            ->map(fn (array $r): array => [
                $r['model'],
                $r['quality_pct'].'%',
                number_format($r['avg_secs_per_turn'], 2).'s',
                number_format($r['total_secs'], 1).'s',
                $r['checks_passed'].'/'.$r['checks_total'],
            ])
            ->all();

        $this->newLine();
        $this->table(['Model', 'Quality', 'Avg/turn', 'Total', 'Checks'], $rows);
    }
}
