<?php

declare(strict_types=1);

namespace App\Http\Controllers\Household;

use App\Http\Controllers\Controller;
use App\Http\Requests\CustomField\StoreCustomFieldRequest;
use App\Http\Requests\CustomField\UpdateCustomFieldRequest;
use App\Models\CustomField;
use App\Models\Item;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CustomFieldController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('household/CustomFields', [
            'fields' => CustomField::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn (CustomField $field): array => $this->present($field))
                ->values(),
        ]);
    }

    public function store(StoreCustomFieldRequest $request): RedirectResponse
    {
        CustomField::create([
            'name' => $request->string('name')->trim()->value(),
            'type' => $request->string('type')->value(),
            'is_searchable' => $request->boolean('searchable'),
            'sort_order' => (int) CustomField::max('sort_order') + 1,
        ]);

        return back();
    }

    public function update(UpdateCustomFieldRequest $request, CustomField $customField): RedirectResponse
    {
        abort_if($customField->is_system, 403);

        $customField->update([
            'name' => $request->string('name')->trim()->value(),
            'type' => $request->string('type')->value(),
            'is_searchable' => $request->boolean('searchable'),
        ]);

        // Toggling searchability changes what lands in the index for every item
        // carrying this field, so rebuild the whole Scout index.
        if ($customField->wasChanged('is_searchable')) {
            Item::removeAllFromSearch();
            Item::makeAllSearchable();
        }

        return back();
    }

    public function destroy(CustomField $customField): RedirectResponse
    {
        abort_if($customField->is_system, 403);

        $customField->delete();

        return back();
    }

    /**
     * @return array{id: int, name: string, key: string, type: string, is_searchable: bool, is_system: bool}
     */
    private function present(CustomField $field): array
    {
        return [
            'id' => $field->id,
            'name' => $field->name,
            'key' => $field->key,
            'type' => $field->type->value,
            'is_searchable' => $field->is_searchable,
            'is_system' => $field->is_system,
        ];
    }
}
