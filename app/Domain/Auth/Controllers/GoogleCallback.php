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
            $googleUser = Socialite::driver('google')
                ->redirectUrl(env('LEAN_GOOGLE_REDIRECT_URI', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]/auth/googleCallback"))
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
