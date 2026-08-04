<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Repositories\Contracts\DatabaseTransactionRepositoryInterface;
use Illuminate\Support\Facades\DB;

/**
 * Executes repository operations inside an Eloquent database transaction.
 *
 * This infrastructure implementation keeps transaction management out of
 * application services while preserving an explicit transaction boundary.
 */
final class EloquentDatabaseTransactionRepository implements DatabaseTransactionRepositoryInterface
{
    /**
     * Executes the supplied operation atomically.
     *
     * @template TReturnValue
     *
     * @param  callable(): TReturnValue  $operation  Operation to execute atomically.
     * @return TReturnValue Result returned by the operation.
     */
    public function execute(callable $operation): mixed
    {
        return DB::transaction($operation);
    }
}
