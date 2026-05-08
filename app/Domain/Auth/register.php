<?php

use Leantime\Core\Events\EventDispatcher;
use Leantime\Domain\Auth\Services\GoogleSync;

/**
 * Register event listeners for Auth domain.
 */

// UI Filters for Login/Registration
EventDispatcher::add_filter_listener('leantime.domain.auth.template.login.welcomeText', function ($content, $params) {
    $language = app()->make(\Leantime\Core\Language::class);
    return $language->__('text.welcome_to_leantime_content');
});

EventDispatcher::add_filter_listener('leantime.domain.auth.template.userInvite5.welcomeText', function ($content, $params) {
    $language = app()->make(\Leantime\Core\Language::class);
    return $language->__('text.welcome_to_leantime_content');
});

EventDispatcher::add_filter_listener('leantime.domain.auth.*.belowWelcomeText', function ($content, $params) {
    $quotes = [
        "\"It's the first project management app I've used for more than a week, and it makes sense too.\"<br /><br />- Interior Designer",
        '"For me, Leantime is very cool, because it is lean. Not 3 million options to think about. The more you put in, the more it could be overloaded."<br /><br />- Open Source User',
        '"We are a small digital marketing agency and have been using Leantime for a couple of months after switching from ClickUp. Getting great feedback from our clients."<br /><br />- CEO'
    ];
    $random = rand(0, 2);
    return '<div class="socialProofContent"><i>'.$quotes[$random].'</i></div>';
});

// Google Synchronization Listeners
EventDispatcher::add_event_listener(
    'leantime.domain.tickets.services.tickets.*.ticket_created',
    [GoogleSync::class, 'syncTicketToGoogle'],
    5
);

EventDispatcher::add_event_listener(
    'leantime.domain.tickets.services.tickets.*.ticket_updated',
    [GoogleSync::class, 'syncTicketToGoogle'],
    5
);

EventDispatcher::add_event_listener(
    'leantime.*.tickets.*.ticket_created',
    [GoogleSync::class, 'syncTicketToGoogle'],
    5
);

EventDispatcher::add_event_listener(
    'leantime.*.tickets.*.ticket_updated',
    [GoogleSync::class, 'syncTicketToGoogle'],
    5
);
