<?php

namespace App\Core; // Or App\Http

class Request
{
    public array $server;
    public array $get;
    public array $post;
    public array $files;
    public ?string $body;
    public array $headers = [];
    private array $attributes = []; // For storing things like the authenticated user

    public function __construct()
    {
        $this->server = $_SERVER;
        $this->get = $_GET;
        $this->post = $_POST;
        $this->files = $_FILES;
        $this->body = file_get_contents('php://input');

        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $headerKey = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
                $this->headers[$headerKey] = $value;
            }
        }
    }

    public function getPath(): string
    {
        return rtrim(parse_url($this->server['REQUEST_URI'], PHP_URL_PATH), '/');
    }

    public function getMethod(): string
    {
        return $this->server['REQUEST_METHOD'];
    }

    public function getHeader(string $name, $default = null): ?string
    {
        $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', $name))));
        return $this->headers[$name] ?? $default;
    }

    // Methods to manage attributes (like authenticated user)
    public function setAttribute(string $key, $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function getAttribute(string $key, $default = null)
    {
        return $this->attributes[$key] ?? $default;
    }

    public function getAllAttributes(): array
    {
        return $this->attributes;
    }
}
