<?php

declare(strict_types=1);

namespace App\Tests\Utils;

use App\Utils\Markdown;
use PHPUnit\Framework\TestCase;

class MarkdownTest extends TestCase
{
    private Markdown $markdown;

    protected function setUp(): void
    {
        $this->markdown = new Markdown();
    }

    public function testToHtmlConvertsBasicMarkdown(): void
    {
        $result = $this->markdown->toHtml('# Hello World');
        $this->assertIsString($result);
        $this->assertStringContainsString('<h1>Hello World</h1>', $result);
    }

    public function testToHtmlConvertsBoldText(): void
    {
        $result = $this->markdown->toHtml('**bold text**');
        $this->assertIsString($result);
        $this->assertStringContainsString('<strong>bold text</strong>', $result);
    }

    public function testToHtmlConvertsItalicText(): void
    {
        $result = $this->markdown->toHtml('*italic text*');
        $this->assertIsString($result);
        $this->assertStringContainsString('<em>italic text</em>', $result);
    }

    public function testToHtmlConvertsLinks(): void
    {
        $result = $this->markdown->toHtml('[Symfony](https://symfony.com)');
        $this->assertIsString($result);
        $this->assertStringContainsString('<a href="https://symfony.com">Symfony</a>', $result);
    }

    public function testToHtmlConvertsLists(): void
    {
        $result = $this->markdown->toHtml("- item 1\n- item 2");
        $this->assertIsString($result);
        $this->assertStringContainsString('<ul>', $result);
        $this->assertStringContainsString('<li>item 1</li>', $result);
    }

    public function testToHtmlWithEmptyString(): void
    {
        $result = $this->markdown->toHtml('');

        $this->assertSame('', $result);
    }

    public function testToHtmlReturnsMixedType(): void
    {
        $result = $this->markdown->toHtml('# Test');

        $this->assertIsString($result);
    }
}
