<?php

declare(strict_types=1);

namespace Osticket\Mail\Tests\Unit\Support;

use InvalidArgumentException;
use Osticket\Mail\Support\InlineImage;
use PHPUnit\Framework\TestCase;

final class InlineImageTest extends TestCase
{
    public function test_it_creates_inline_image(): void
    {
        $img = new InlineImage('logo.png', 'image/png', 'cid123@example.com', 'bin');

        $this->assertSame('logo.png', $img->filename());
        $this->assertSame('image/png', $img->mimeType());
        $this->assertSame('cid123@example.com', $img->contentId());
        $this->assertSame('bin', $img->content());
    }

    public function test_it_rejects_whitespace_in_content_id(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new InlineImage('a.png', 'image/png', 'bad id', 'x');
    }
}
