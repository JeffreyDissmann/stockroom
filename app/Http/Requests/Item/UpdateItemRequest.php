<?php

declare(strict_types=1);

namespace App\Http\Requests\Item;

use App\Enums\ItemType;
use App\Http\Requests\Item\Concerns\HasCustomFieldRules;
use App\Http\Requests\Item\Concerns\HasItemDetailRules;
use App\Models\Item;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateItemRequest extends FormRequest
{
    use HasCustomFieldRules;
    use HasItemDetailRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'icon' => ['nullable', 'string', 'max:40'],
            'type' => ['required', new Enum(ItemType::class)],
            'tags' => ['array'],
            'tags.*' => ['integer', Rule::exists('tags', 'id')],
            ...$this->detailRules(),
            ...$this->customFieldRules(),
            'contents_disposition' => [
                Rule::requiredIf(fn (): bool => $this->sellingAFullContainer()),
                // The form posts this field on every save, empty when there is
                // nothing to decide. Without `nullable` the in: rule still
                // judges that empty value and rejects an ordinary sale.
                'nullable',
                Rule::in(['sold', 'kept']),
            ],
        ];
    }

    /**
     * Is this edit the moment a container with things in it becomes sold?
     *
     * Only then does the caller have to say what became of the contents. A
     * plain item, an empty container, or an edit to an already-sold one all
     * save silently — asking every time is how a question gets clicked past.
     */
    private function sellingAFullContainer(): bool
    {
        $item = $this->route('item');

        if (! $item instanceof Item || $item->type !== ItemType::Container) {
            return false;
        }

        // Already sold: this is an edit to the sale, not the sale itself, and
        // the contents were settled when it first went.
        if (filled($item->sold_date)) {
            return false;
        }

        return filled($this->input('sold_date')) && $item->children()->exists();
    }
}
