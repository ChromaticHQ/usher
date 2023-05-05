<?php

namespace Usher\Robo\Plugin\Commands;

use DrupalFinder\DrupalFinder;
use Robo\Exception\TaskException;
use Robo\Result;
use Robo\Tasks;
use Usher\Robo\Plugin\Traits\SentryNotifierTrait;

/**
 * Robo commands related to continuous integration.
 */
class DrupalCronCommands extends Tasks
{
    use SentryNotifierTrait;

    /**
     * Drupal root directory.
     *
     * @var string
     */
    protected $drupalRoot;

    /**
     * RoboFile constructor.
     */
    public function __construct()
    {
        // Treat this command like bash -e and exit as soon as there's a failure.
        $this->stopOnFail();

        // Find Drupal root path.
        $drupalFinder = new DrupalFinder();
        $drupalFinder->locateRoot(getcwd());
        $this->drupalRoot = $drupalFinder->getDrupalRoot();
    }

    /**
     * Run Drupal cron.
     *
     * @param string $siteName
     *   The Drupal site shortname. Optional.
     * @param string $docroot
     *   The Drupal document root directory. Optional.
     *
     * @return \Robo\Result
     *   The result of the set of tasks.
     */
    public function drupalRunCron(
        string $siteName = 'default',
        string $docroot = 'web',
        array $options = ['skip-sentry' => false]
    ): Result {
        $skipSentry = $options['skip-sentry'];
        if (!$skipSentry) {
            $this->sentryJobBeginning();
        }
        $result = null;
        try {
            $result = $this->taskExec("$this->drupalRoot/../vendor/bin/drush cron")
                ->arg('cron')
                ->option('--yes')
                ->dir("$docroot/sites/$siteName")
                ->run();
        } catch (TaskException $e) {
            if (!$skipSentry) {
                $this->sentryJobFailed();
                throw new TaskException($this, "TKTK");
            }
        }

        if (!$skipSentry) {
            $this->sentryJobCompleted();
        }
        return $result;
    }
}
