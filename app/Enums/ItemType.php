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
        return match ($this) {
            self::Room => 'Room',
            self::Container => 'Container',
            self::Item => 'Item',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Room => 'home',
            self::Container => 'box',
            self::Item => 'package',
        };
    }
}
