<?php

declare(strict_types=1);

namespace Osticket\Mail\Tests\Unit\Support;

use InvalidArgumentException;
use Osticket\Mail\Support\Attachment;
use PHPUnit\Framework\TestCase;

final class AttachmentTest extends TestCase
{
    public function test_it_creates_attachment(): void
    {
        $a = new Attachment('file.txt', 'text/plain', 'hello');

        $this->assertSame('file.txt', $a->filename());
        $this->assertSame('text/plain', $a->mimeType());
        $this->assertSame('hello', $a->content());
    }

    public function test_it_rejects_empty_filename(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Attachment('', 'text/plain', 'x');
    }
}
