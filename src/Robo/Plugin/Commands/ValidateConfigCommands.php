<?php

namespace Usher\Robo\Plugin\Commands;

use DrupalFinder\DrupalFinderComposerRuntime;
use Robo\Exception\TaskException;
use Robo\Result;
use Robo\Tasks;
use Usher\Robo\Plugin\Traits\GitHubStatusTrait;

/**
 * Robo commands related to validating Drupal configuration state.
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
        $drupalFinder = new DrupalFinderComposerRuntime();
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
    public function validateDrupalConfig(
        string $siteDirs = 'default',
        array $options = ['set-pr-status' => false],
    ): Result {
        $this->io()->title('validate drupal configuration.');

        ['set-pr-status' => $setPrStatus] = $options;
        if ($setPrStatus) {
            $this->setGitHubStatusPending(gitHubCheckName: self::GITHUB_STATUS_CHECK_NAME);
        }

        $result = null;
        $sites = explode(separator: ',', string: $siteDirs);
        foreach ($sites as $siteDir) {
            // Clear the "config" cache bin before we verify config status to
            // improve the accuracy of this check.
            // @see https://github.com/drush-ops/drush/pull/3861#issuecomment-453767694
            // @see https://www.drush.org/11.x/commands/cache_clear/
            $result = $this->taskExec("$this->drupalRoot/../vendor/bin/drush cache:clear bin config")
                ->dir("$this->drupalRoot/sites/$siteDir/")
                ->run();
            $result = $this->taskExec("$this->drupalRoot/../vendor/bin/drush config:status --format=json")
                ->dir("$this->drupalRoot/sites/$siteDir/")
                ->printOutput(false)
                ->run();
            $drushOutput = trim((string) $result->getOutputData());
            $configJson = json_decode($drushOutput, null, 512, JSON_THROW_ON_ERROR);
            if (!is_array($configJson) || $configJson !== []) {
                $this->say($drushOutput);
                if ($setPrStatus) {
                    $this->setGitHubStatusError(
                        gitHubCheckName: self::GITHUB_STATUS_CHECK_NAME,
                        gitHubCheckDescription: 'Drupal config validation failed!'
                    );
                }
                throw new TaskException(
                    $this,
                    'Drupal database configuration does not match the tracked file system configuration.'
                );
            }
        }

        $this->say('Drupal database configuration matches the tracked file system configuration.');
        if ($setPrStatus) {
            $this->setGitHubStatusSuccess(
                gitHubCheckName: self::GITHUB_STATUS_CHECK_NAME,
                gitHubCheckDescription: 'Drupal config validation passed!'
            );
        }
        return $result;
    }
}
