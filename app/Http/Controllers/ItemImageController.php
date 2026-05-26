<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Item\StoreItemImagesRequest;
use App\Models\Item;
use App\Models\ItemImage;
use App\Services\ItemImageProcessor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ItemImageController extends Controller
{
    public function __construct(private readonly ItemImageProcessor $processor) {}

    public function store(StoreItemImagesRequest $request, Item $item): RedirectResponse
    {
        $files = $request->file('images');

        foreach ($files as $file) {
            $this->processor->store($item, $file);
        }

        $item->logImagesAdded(count($files));

        return back();
    }

    public function update(Request $request, Item $item, ItemImage $image): RedirectResponse
    {
        $data = $request->validate([
            'is_primary' => ['sometimes', 'boolean'],
        ]);

        if (($data['is_primary'] ?? false) === true) {
            $image->is_primary = true;
            $image->save();
        }

        return back();
    }

    public function destroy(Item $item, ItemImage $image): RedirectResponse
    {
        $wasPrimary = $image->is_primary;
        $image->delete();

        if ($wasPrimary) {
            $next = $item->images()->orderBy('sort_order')->first();
            if ($next !== null) {
                $next->is_primary = true;
                $next->save();
            }
        }

        return back();
    }

    public function reorder(Request $request, Item $item): RedirectResponse
    {
        $existingIds = $item->images()->pluck('id')->all();

        $data = $request->validate([
            'ids' => ['required', 'array', 'size:'.count($existingIds)],
            'ids.*' => ['integer', Rule::in($existingIds)],
        ]);

        $ids = array_map(intval(...), $data['ids']);
        if (count(array_unique($ids)) !== count($ids)) {
            throw ValidationException::withMessages(['ids' => 'Each image id must appear exactly once.']);
        }

        DB::transaction(function () use ($ids, $item): void {
            foreach ($ids as $index => $id) {
                $item->images()->where('id', $id)->update(['sort_order' => $index]);
            }
        });

        return back();
    }
}
