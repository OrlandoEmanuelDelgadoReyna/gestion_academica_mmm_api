<?php

declare(strict_types=1);

namespace App\Support;

use ArrayAccess;

/**
 * Flysystem 3.25.1 always forwards an object ACL to PutObject.
 * Tigris rejects object ACLs; the bucket itself stays private.
 */
final class OmitS3ObjectAcl
{
    public static function handle(object $command): void
    {
        if (! $command instanceof ArrayAccess) {
            return;
        }

        if ($command->offsetExists('ACL')) {
            $command->offsetUnset('ACL');
        }
    }
}
