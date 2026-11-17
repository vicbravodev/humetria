<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The demo company's people write from a reserved domain (`.test`, RFC 6761): mail only to it never leaves (BlockDemoMail).
 */
final class DemoMail
{
    public const string DOMAIN = 'demo.humetria.test';
}
