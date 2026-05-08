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
        $redirectUri = "https://leantime-tool-production.up.railway.app/auth/googleCallback";

        return Socialite::driver('google')
            ->redirectUrl($redirectUri)
            ->setScopes(['openid', 'profile', 'email', 'https://www.googleapis.com/auth/calendar', 'https://www.googleapis.com/auth/tasks'])
            ->with(['access_type' => 'offline', 'prompt' => 'consent'])
            ->redirect();
    }
}
