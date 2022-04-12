<?php

namespace Usher\Robo\Plugin\Commands;

use DrupalFinder\DrupalFinder;
use Robo\Exception\TaskException;
use Robo\Tasks;

/**
 * Robo commands related to changing development modes.
 */
class ValidateConfigCommands extends Tasks
{
    /**
     * Drupal root directory.
     *
     * @var string
     */
    protected $drupalRoot;

    /**
     * Class constructor.
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
     * Validate Drupal configuration status.
     *
     * @param string $siteDir
     *   The Drupal site directory.
     *
     * @aliases vdc
     *
     * @throws \Robo\Exception\TaskException
     */
    public function validateDrupalConfig($siteDir = 'default'): void
    {
        $result = $this->taskExec("$this->drupalRoot/../vendor/bin/drush config:status --format=json")
            ->dir("$this->drupalRoot/sites/$siteDir/")
            ->printOutput(false)
            ->run();
        $drushOutput = trim($result->getOutputData());
        $configJson = json_decode($drushOutput);
        // We trimmed $drushOutput, so the OK-output of NULL has become an empty
        // string.
        if ($configJson !== '') {
            $this->say($drushOutput);
            throw new TaskException(
                $this,
                'Drupal database configuration does not match the tracked file system configuration.'
            );
        }
        $this->say('Drupal database configuration matches the tracked file system configuration.');
    }
}
