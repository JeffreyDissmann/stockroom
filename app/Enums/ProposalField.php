<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The item attributes the review agent may propose a value for.
 *
 * Deliberately a closed set: it is what the accept path is allowed to write,
 * so a model inventing a field name can never reach `Item::update()`.
 */
enum ProposalField: string
{
    case Description = 'description';
    case Manufacturer = 'manufacturer';
    case ModelNumber = 'model_number';
    case SerialNumber = 'serial_number';

    public function label(): string
    {
        return __('enums.proposal_field.'.$this->value);
    }
}
