<?php

namespace App\Contracts;

/**
 * Messages implementing this interface are written to the audit log.
 */
interface Auditable
{
    public function auditDescription(): string;
}
