<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A backup could not be taken, or an archive could not be produced.
 *
 * Carries a message meant to be read by the superadmin on the Backups page,
 * so it should say what went wrong and what to do about it — not a stack
 * trace. BackupService records the failed run before throwing, so catching
 * this never loses the audit trail.
 */
class BackupException extends RuntimeException
{
}
