<?php

declare(strict_types=1);

namespace Tests\Unit\Stubs;

use Core\Model;

/**
 * Concrete stub with a fixed table name for testing Core\Model methods.
 */
class ConcreteModel extends Model
{
    protected string $table = 'items';
}
