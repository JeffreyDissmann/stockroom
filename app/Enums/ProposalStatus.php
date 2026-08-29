<?php

declare(strict_types=1);

namespace App\Enums;

enum ProposalStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';

    public function label(): string
    {
        return __('enums.proposal_status.'.$this->value);
    }
}
