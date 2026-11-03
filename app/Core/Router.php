<?php
/**
 * Routeur minimal.
 *
 *   $router->get('/projet/{slug}', [ProjectController::class, 'show']);
 *   $router->post('/contact', [ContactController::class, 'send']);
 *   $router->resource('/admin/tags', TagController::class);
 *
 * Paramètres : {slug} => [a-z0-9-]+ ; {id} => entier positif.
 * Toute requête POST est vérifiée contre le CSRF AVANT d'atteindre le contrôleur :
 * impossible d'oublier la protection sur un formulaire.
 */

declare(strict_types=1);

namespace App\Core;

final class Router
{
    /** @var list<array{method:string, pattern:string, regex:string, handler:array{0:class-string,1:string}}> */
    private array $routes = [];

    /** @param array{0:class-string,1:string} $handler */
    public function get(string $pattern, array $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    /** @param array{0:class-string,1:string} $handler */
    public function post(string $pattern, array $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    /**
     * Déclare les 6 routes CRUD classiques d'une ressource d'administration :
     *   GET  {base}                 index
     *   GET  {base}/nouveau         create
     *   POST {base}                 store
     *   GET  {base}/{id}/modifier   edit
     *   POST {base}/{id}            update
     *   POST {base}/{id}/supprimer  destroy
     *
     * @param class-string $controller
     */
    public function resource(string $base, string $controller): void
    {
        $this->get($base, [$controller, 'index']);
        $this->get($base . '/nouveau', [$controller, 'create']);
        $this->post($base, [$controller, 'store']);
        $this->get($base . '/{id}/modifier', [$controller, 'edit']);
        $this->post($base . '/{id}', [$controller, 'update']);
        $this->post($base . '/{id}/supprimer', [$controller, 'destroy']);
    }

    public function dispatch(string $method, string $path): void
    {
        // Normalisation : « /parcours/ » => redirection vers « /parcours ».
        if ($path !== '/' && str_ends_with($path, '/')) {
            $target = rtrim($path, '/');
            $query  = (string) ($_SERVER['QUERY_STRING'] ?? '');
            redirect(($target === '' ? '/' : $target) . ($query !== '' ? '?' . $query : ''), 301);
        }

        // HEAD est traité comme GET (PHP n'envoie pas le corps).
        $effectiveMethod = $method === 'HEAD' ? 'GET' : $method;
        $allowed = [];

        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }
            if ($route['method'] !== $effectiveMethod) {
                $allowed[] = $route['method'];
                continue;
            }

            if ($effectiveMethod === 'POST') {
                Csrf::verifyRequest();
            }

            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            [$class, $action] = $route['handler'];
            $controller = new $class();
            $controller->{$action}(...array_values($params));
            return;
        }

        if ($allowed !== []) {
            header('Allow: ' . implode(', ', array_unique($allowed)));
            throw new HttpException(405);
        }
        throw new HttpException(404);
    }

    /** @param array{0:class-string,1:string} $handler */
    private function add(string $method, string $pattern, array $handler): void
    {
        $regex = preg_replace_callback('/\{(\w+)\}/', static function (array $m): string {
            return match ($m[1]) {
                'id'    => '(?P<id>[1-9][0-9]{0,9})',
                default => '(?P<' . $m[1] . '>[a-z0-9]+(?:-[a-z0-9]+)*)',
            };
        }, $pattern);

        $this->routes[] = [
            'method'  => $method,
            'pattern' => $pattern,
            'regex'   => '#^' . $regex . '$#',
            'handler' => $handler,
        ];
    }
}
