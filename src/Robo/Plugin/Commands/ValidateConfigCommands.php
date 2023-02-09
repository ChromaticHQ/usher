<?php

namespace Usher\Robo\Plugin\Commands;

use DrupalFinder\DrupalFinder;
use Robo\Exception\TaskException;
use Robo\Tasks;
use Usher\Robo\Plugin\Traits\GitHubStatusTrait;

/**
 * Robo commands related to changing development modes.
 */
class ValidateConfigCommands extends Tasks
{
    use GitHubStatusTrait;

    /**
     * The name of the GitHub status check, if set.
     *
     * @var string
     */
    protected const GITHUB_STATUS_CHECK_NAME = 'ci/configuration-check';

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
     * @param string $siteDirs
     *   A comma separated list of Drupal site directories.
     * @option set-pr-status
     *   Use this flag in Tugboat environments to set the GitHub status check
     *   on the associated pull request.
     * @aliases vdc
     *
     * @throws \Robo\Exception\TaskException
     */
    public function validateDrupalConfig($siteDirs = 'default', array $options = ['set-pr-status' => false]): void
    {
        if ($options['set-pr-status']) {
            $this->setGitHubStatusPending(self::GITHUB_STATUS_CHECK_NAME);
        }
        $sites = explode(',', $siteDirs);
        foreach ($sites as $siteDir) {
            $result = $this->taskExec("$this->drupalRoot/../vendor/bin/drush config:status --format=json")
                ->dir("$this->drupalRoot/sites/$siteDir/")
                ->printOutput(false)
                ->run();
            $drushOutput = trim($result->getOutputData());
            $configJson = json_decode($drushOutput);
            if (!is_array($configJson) || count($configJson) > 0) {
                $this->say($drushOutput);
                if ($options['set-pr-status']) {
                    $this->setGitHubStatusError(self::GITHUB_STATUS_CHECK_NAME, 'Drupal config validation failed!');
                }
                throw new TaskException(
                    $this,
                    'Drupal database configuration does not match the tracked file system configuration.'
                );
            }
        }

        $this->say('Drupal database configuration matches the tracked file system configuration.');
        if ($options['set-pr-status']) {
            $this->setGitHubStatusSuccess(self::GITHUB_STATUS_CHECK_NAME, 'Drupal config validation passed!');
        }
    }
}
