<?php

namespace Leantime\Core\Middleware;

use Closure;
use Leantime\Core\Controller\Frontcontroller;
use Leantime\Core\Events\DispatchesEvents;
use Leantime\Core\Http\IncomingRequest;
use Leantime\Domain\Setting\Repositories\Setting as SettingRepository;
use Symfony\Component\HttpFoundation\Response;

class Installed
{
    use DispatchesEvents;

    /**
     * Check if Leantime is installed
     *
     * @param  \Closure(IncomingRequest): Response  $next
     *
     * @throws BindingResolutionException
     **/
    public function handle(IncomingRequest $request, Closure $next): Response
    {
        return $next($request);
    }

    /**
     * Set installed
     */
    private function setInstalled(): void
    {
        session(['isInstalled' => true]);
    }

    /**
     * Set uninstalled
     */
    private function setUninstalled(): void
    {
        session(['isInstalled' => false]);

        if (session()->exists('userdata')) {
            session()->forget('userdata');
        }
    }

    /**
     * Redirect to install
     *
     * @throws BindingResolutionException
     */
    private function redirectToInstall(IncomingRequest $request): Response|false
    {
        $frontController = app()->make(Frontcontroller::class);

        $allowedRoutes = ['install', 'install.update', 'api.i18n'];
        $allowedRoutes = self::dispatchFilter('allowedRoutes', $allowedRoutes);
        $route = $request->getCurrentRoute();
        $uri = $_SERVER['REQUEST_URI'] ?? '';

        if (in_array($request->getCurrentRoute(), $allowedRoutes) || str_contains($uri, 'install')) {
            return false;
        }

        $route = BASE_URL.'/install';
        $route = self::dispatchFilter('redirectroute', $route);

        return $frontController::redirect($route);
    }
}
