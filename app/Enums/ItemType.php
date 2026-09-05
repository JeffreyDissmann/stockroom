<?php

declare(strict_types=1);

namespace App\Enums;

enum ItemType: string
{
    case Room = 'room';
    case Container = 'container';
    case Item = 'item';

    public function label(): string
    {
        return __('enums.item_type.'.$this->value);
    }

    public function icon(): string
    {
        return match ($this) {
            self::Room => 'home',
            self::Container => 'box',
            self::Item => 'package',
        };
    }

    /**
     * Whether the acquisition/warranty/sale detail fields apply to this type.
     * A Room is a place, not a possession — those fields are meaningless for it.
     */
    public function hasDetailFields(): bool
    {
        return $this !== self::Room;
    }

    /**
     * Everything a UI surface needs to render this type. Composed here because
     * six controllers used to build the same four keys by hand, and they had
     * already drifted — the command palette shipped `{value, label}` while the
     * item cards expected an icon too.
     *
     * @return array{value: string, label: string, icon: string, details: bool}
     */
    public function descriptor(): array
    {
        return [
            'value' => $this->value,
            'label' => $this->label(),
            'icon' => $this->icon(),
            'details' => $this->hasDetailFields(),
        ];
    }
}
