<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;

/**
 * A customer sees only their own tickets (IDOR); staff need tickets.view-all.
 */
class TicketPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('tickets.view-all');
    }

    public function view(User $actor, Ticket $ticket): bool
    {
        return ($ticket->user_id !== null && $ticket->user_id === $actor->id) || $actor->can('tickets.view-all');
    }
}
