<?php

declare(strict_types=1);

namespace Osticket\Mail\Tests\Unit\Extensions;

use Osticket\Mail\Extensions\Attachments\AttachmentScannerInterface;
use Osticket\Mail\Extensions\Dkim\DkimSignerInterface;
use Osticket\Mail\Extensions\Dmarc\DmarcValidatorInterface;
use Osticket\Mail\Message\Message;
use Osticket\Mail\Support\Envelope;
use Osticket\Mail\Support\Attachment;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class ExtensionContractSignaturesTest extends TestCase
{
    public function test_dkim_signer_signature_is_stable(): void
    {
        $m = new ReflectionMethod(DkimSignerInterface::class, 'sign');

        self::assertSame(DkimSignerInterface::class, $m->getDeclaringClass()->getName());
        self::assertSame(2, $m->getNumberOfParameters());
        self::assertSame(1, $m->getNumberOfRequiredParameters());

        self::assertSame(Message::class, $m->getParameters()[0]->getType()?->getName());
        self::assertSame('array', $m->getParameters()[1]->getType()?->getName());
    }

    public function test_dmarc_validator_signature_is_stable(): void
    {
        $m = new ReflectionMethod(DmarcValidatorInterface::class, 'validate');

        self::assertSame(3, $m->getNumberOfParameters());
        self::assertSame(2, $m->getNumberOfRequiredParameters());

        self::assertSame(Message::class, $m->getParameters()[0]->getType()?->getName());
        self::assertSame(Envelope::class, $m->getParameters()[1]->getType()?->getName());
        self::assertSame('array', $m->getParameters()[2]->getType()?->getName());
    }

    public function test_attachment_scanner_signature_is_stable(): void
    {
        $m = new ReflectionMethod(AttachmentScannerInterface::class, 'scan');

        self::assertSame(2, $m->getNumberOfParameters());
        self::assertSame(1, $m->getNumberOfRequiredParameters());

        self::assertSame('array', $m->getParameters()[0]->getType()?->getName());
        self::assertSame('array', $m->getParameters()[1]->getType()?->getName());

        // Ensure the doc expects attachments (not enforced by runtime, but we can at least ensure interface exists).
        self::assertTrue(\interface_exists(AttachmentScannerInterface::class));
        self::assertTrue(\class_exists(Attachment::class));
    }
}
