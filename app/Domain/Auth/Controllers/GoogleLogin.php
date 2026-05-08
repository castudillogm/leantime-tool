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
        $protocol = (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') || (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || (strpos($_SERVER['HTTP_HOST'], 'railway.app') !== false) ? 'https' : 'http';
        $redirectUri = $_ENV['LEAN_GOOGLE_REDIRECT_URI'] ?? $_SERVER['LEAN_GOOGLE_REDIRECT_URI'] ?? env('LEAN_GOOGLE_REDIRECT_URI', "$protocol://$_SERVER[HTTP_HOST]/auth/googleCallback");

        return Socialite::driver('google')
            ->redirectUrl($redirectUri)
            ->setScopes(['openid', 'profile', 'email'])
            ->with(['access_type' => 'offline', 'prompt' => 'consent'])
            ->redirect();
    }
}
