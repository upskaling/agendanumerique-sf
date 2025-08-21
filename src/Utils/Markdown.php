<?php

// https://symfony.com/doc/3.x/best_practices/templates.html#twig-extensions

declare(strict_types=1);

namespace App\Utils;

class Markdown
{
    private $parser;

    public function __construct()
    {
        $this->parser = new \Parsedown();
    }

    public function toHtml($text)
    {
        return $this->parser->text($text);
    }
}
