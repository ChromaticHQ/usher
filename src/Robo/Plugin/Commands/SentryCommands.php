<?php

namespace Usher\Robo\Plugin\Commands;

use Robo\Exception\TaskException;
use Robo\Result;
use Robo\Robo;
use Robo\Tasks;

/**
 * Robo commands related to Sentry integration.
 */
class SentryCommands extends Tasks
{
    /**
     * RoboFile constructor.
     */
    public function __construct()
    {
        // Treat this command like bash -e and exit as soon as there's a failure.
        $this->stopOnFail();
    }

    /**
     * Tag a release in Sentry.
     *
     * @param string $webhookUrl
     *   URL for the webhook to TK.
     * @param string $releaseId
     *   The ID to tag the release with.
     *
     * @aliases tag
     *
     * @return \Robo\Result
     *   The result of the set of tasks.
     */
    public function sentryTagRelease(string $webhookUrl, string $releaseId): Result
    {
        $this->io()->title('tag release in sentry.');
        // curl $webhookUrl \
        //   -X POST \
        //   -H 'Content-Type: application/json' \
        //   -d '{"version": "abcdefg"}'
        return $this->taskExec('TK')->run();
    }
}
