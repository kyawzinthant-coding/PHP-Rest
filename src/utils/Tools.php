<?php

namespace App\utils;

class Tools
{

    function generateSlug(string $name): string
    {
        // Convert to lowercase
        $slug = strtolower($name);

        // Replace non-letter or digits with hyphen
        $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug);

        // Trim hyphens from beginning and end
        $slug = trim($slug, '-');

        return $slug;
    }
}
