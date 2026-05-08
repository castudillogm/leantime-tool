<?php

use Leantime\Core\Events\EventDispatcher;
use Leantime\Domain\Auth\Services\GoogleSync;

/**
 * Register event listeners for Google Synchronization.
 */

EventDispatcher::add_event_listener(
    'Leantime\Domain\Tickets\Services\Tickets.ticket_created',
    [GoogleSync::class, 'syncTicketToGoogle'],
    5
);

EventDispatcher::add_event_listener(
    'Leantime\Domain\Tickets\Services\Tickets.ticket_updated',
    [GoogleSync::class, 'syncTicketToGoogle'],
    5
);
