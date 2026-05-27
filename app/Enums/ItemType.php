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
}
