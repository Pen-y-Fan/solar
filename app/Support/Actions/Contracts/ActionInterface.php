<?php

declare(strict_types=1);

namespace App\Support\Actions\Contracts;

use App\Support\Actions\ActionResult;

/**
 * Generic contract for domain actions to standardise execution.
 * Implementations should be side-effect-aware and exception-safe.
 */
interface ActionInterface
{
    /**
     * Execute the action.
     *
     * Implementations should catch recoverable exceptions and return ActionResult::failure with a meaningful message.
     * Unrecoverable errors may still bubble up.
     */
    public function execute(): ActionResult;
}
