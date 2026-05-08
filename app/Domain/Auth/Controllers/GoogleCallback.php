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
            $redirectUri = "https://leantime-tool-production.up.railway.app/auth/googleCallback";

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
                'role' => 20, // Numeric role (Editor)
                'password' => bin2hex(random_bytes(16)), // Generate a random password for SSO users
                'clientId' => 0,
                'source' => 'google',
                'status' => 'a',
            ];
            $userId = $this->userRepo->addUser($userArray);
            if (!$userId) {
                throw new \Exception("Failed to create user in database");
            }
            $user = $this->userRepo->getUser($userId);
        }

        // Ensure we have all required keys for editUser repository method
        if (!isset($user['user']) && isset($user['username'])) {
            $user['user'] = $user['username'];
        }

        // Store tokens for later sync (Calendar/Tasks)
        $settings = !empty($user['settings']) ? unserialize($user['settings']) : [];
        if (!is_array($settings)) {
            $settings = [];
        }
        
        $settings['google_token'] = $googleUser->token;
        $settings['google_refresh_token'] = $googleUser->refreshToken;
        $settings['google_expires_in'] = $googleUser->expiresIn;
        
        $user['settings'] = serialize($settings);
        
        // Final check for editUser compatibility
        $user['phone'] = $user['phone'] ?? '';
        $user['clientId'] = $user['clientId'] ?? 0;
        
        $this->userRepo->editUser($user, $user['id']);

        $this->authService->setUserSession($user, true);

        return FrontcontrollerCore::redirect(BASE_URL . '/dashboard/home');
    }
}
