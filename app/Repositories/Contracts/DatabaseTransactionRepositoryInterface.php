<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

/**
 * Defines the transaction boundary used by application services.
 *
 * Services use this contract to coordinate multiple repository operations
 * atomically without coupling business logic to Eloquent or the DB facade.
 */
interface DatabaseTransactionRepositoryInterface
{
    /**
     * Executes the supplied operation inside a database transaction.
     *
     * @template TReturnValue
     *
     * @param  callable(): TReturnValue  $operation  Operation to execute atomically.
     * @return TReturnValue Result returned by the operation.
     */
    public function execute(callable $operation): mixed;
}
