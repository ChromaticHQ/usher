<?php

namespace Usher\Robo\Plugin\Traits;

use Robo\Result;

/**
 * Trait to provide database download functionality to Robo commands.
 */
trait ResultCheckTrait
{
    /**
     * Check if accumulated Robo tasks within a Result were all successful.
     *
     * @param \Robo\Result $result
     *   The primary result to check within.
     *
     * @return bool
     *   Boolean indicating if all tasks within a result were successful.
     *
     * @throws \Robo\Exception\TaskException
     */
    public function resultTasksSuccessful(Result $result): bool
    {
        // Loop through the accumulated results and check for failures.
        /** @var \Robo\Result $taskResult */
        foreach ($result->getIterator() as $taskResult) {
            // Ignore items that are not results.
            if (!$taskResult instanceof Result) {
                continue;
            }
            // Check for failed results.
            if (!$taskResult->wasSuccessful()) {
                return false;
            }
        }
        return true;
    }
}
