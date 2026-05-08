<?php

namespace Leantime\Domain\Auth\Controllers;

use Laravel\Socialite\Facades\Socialite;
use Leantime\Core\Controller\Controller;
use Leantime\Domain\Auth\Services\Auth as AuthService;
use Leantime\Domain\Users\Repositories\Users as UserRepository;
use Leantime\Core\Controller\Frontcontroller as FrontcontrollerCore;
use Symfony\Component\HttpFoundation\Response;

class GoogleCallback extends Controller
{
    private AuthService $authService;
    private UserRepository $userRepo;

    public function init(AuthService $authService, UserRepository $userRepo): void
    {
        $this->authService = $authService;
        $this->userRepo = $userRepo;
    }

    public function run(array $params): Response
    {
        try {
            $protocol = (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') || (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || (strpos($_SERVER['HTTP_HOST'] ?? '', 'railway.app') !== false) ? 'https' : 'http';
            $fullFallback = "$protocol://" . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "/auth/googleCallback";
            
            $redirectUri = $_ENV['LEAN_GOOGLE_REDIRECT_URI'] ?? $_SERVER['LEAN_GOOGLE_REDIRECT_URI'] ?? env('LEAN_GOOGLE_REDIRECT_URI', $fullFallback);
            
            // Safety check: If the redirectUri is not a full URL, force the fallback
            if (strpos($redirectUri, 'http') === false) {
                $redirectUri = $fullFallback;
            }

            $googleUser = Socialite::driver('google')
                ->redirectUrl($redirectUri)
                ->user();
        } catch (\Exception $e) {
            error_log("Google OAuth Error: " . $e->getMessage());
            return FrontcontrollerCore::redirect(BASE_URL . '/auth/login');
        }

        $user = $this->userRepo->getUserByEmail($googleUser->getEmail());

        if (!$user) {
            // Create user
            $userArray = [
                'firstname' => $googleUser->offsetGet('given_name') ?? $googleUser->getName(),
                'lastname' => $googleUser->offsetGet('family_name') ?? '',
                'user' => $googleUser->getEmail(),
                'role' => 'editor', // Default role
                'password' => '',
                'clientId' => '',
                'source' => 'google',
                'status' => 'a',
            ];
            $userId = $this->userRepo->addUser($userArray);
            $user = $this->userRepo->getUser($userId);
        }

        // Store tokens for later sync (Calendar/Tasks)
        $settings = $user['settings'] ? unserialize($user['settings']) : [];
        $settings['google_token'] = $googleUser->token;
        $settings['google_refresh_token'] = $googleUser->refreshToken;
        $settings['google_expires_in'] = $googleUser->expiresIn;
        $user['settings'] = serialize($settings);
        $this->userRepo->editUser($user, $user['id']);

        $this->authService->setUserSession($user, true);

        return FrontcontrollerCore::redirect(BASE_URL . '/dashboard/home');
    }
}
