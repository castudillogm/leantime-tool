<?php

namespace Leantime\Domain\Auth\Controllers;

use Laravel\Socialite\Facades\Socialite;
use Leantime\Core\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;

class GoogleLogin extends Controller
{
    /**
     * run - handle requests
     */
    public function run(array $params): Response
    {
        return Socialite::driver('google')
            ->setScopes(['openid', 'profile', 'email', 'https://www.googleapis.com/auth/calendar', 'https://www.googleapis.com/auth/tasks'])
            ->with(['access_type' => 'offline', 'prompt' => 'consent'])
            ->redirect();
    }
}
