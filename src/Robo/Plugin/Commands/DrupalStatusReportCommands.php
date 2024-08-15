<?php

namespace Usher\Robo\Plugin\Commands;

use DrupalFinder\DrupalFinderComposerRuntime;
use Robo\Exception\TaskException;
use Robo\Robo;
use Robo\Result;
use Robo\Tasks;
use Usher\Robo\Plugin\Traits\GitHubStatusTrait;

/**
 * Robo commands to work with Drupal's status report.
 */
class DrupalStatusReportCommands extends Tasks
{
    use GitHubStatusTrait;

    /**
     * The name of the GitHub status check, if set.
     *
     * @var string
     */
    protected const GITHUB_STATUS_CHECK_NAME = 'ci/drupal-status-report';

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
     * Validate Drupal status report.
     *
     * @param string $siteDirs
     *   A comma separated list of Drupal site directories.
     * @param int $severity
     *   The minimum severity level to show. Defaults to 1 (warning).
     * @option set-pr-status
     *   Use this flag in Tugboat environments to set the GitHub status check
     *   on the associated pull request.
     *
     * @throws \Robo\Exception\TaskException
     */
    public function drupalStatusReport(
        string $siteDirs = 'default',
        int $severity = 1,
        array $options = ['set-pr-status' => false],
    ): Result {
        $this->io()->title('drupal status report.');

        ['set-pr-status' => $setPrStatus] = $options;
        if ($setPrStatus) {
            $this->setGitHubStatusPending(gitHubCheckName: self::GITHUB_STATUS_CHECK_NAME);
        }

        $result = null;
        $sites = explode(separator: ',', string: $siteDirs);
        foreach ($sites as $siteDir) {
            $cmd = [
                "$this->drupalRoot/../vendor/bin/drush",
                "status-report",
                "--format=json",
                "--severity=$severity",
            ];
            if (is_array($ignoreArray = Robo::config()->get('drupal_status_report_ignore_checks'))) {
                $ignoreList = implode(separator: ',', array: $ignoreArray);
                $cmd[] = "--ignore=$ignoreList";
            }
            $result = $this->taskExec(implode(separator: ' ', array: $cmd))
                ->dir("$this->drupalRoot/sites/$siteDir/")
                ->printOutput(false)
                ->run();
            $drushOutput = trim((string) $result->getOutputData());
            $reportJson = json_decode($drushOutput, null, 512, JSON_THROW_ON_ERROR);
            if (!is_array($reportJson) || $reportJson !== []) {
                $this->say($drushOutput);
                if ($setPrStatus) {
                    $this->setGitHubStatusError(
                        gitHubCheckName: self::GITHUB_STATUS_CHECK_NAME,
                        gitHubCheckDescription: 'Drupal status report shows one or more unexpected warnings or errors.'
                    );
                }
                throw new TaskException(
                    $this,
                    'Drupal status report shows one or more unexpected warnings or errors!'
                );
            }
        }

        $this->say('Drupal status report(s) show no unexpected warnings or errors.');
        if ($setPrStatus) {
            $checkDescription = 'Drupal status report shows no unexpected warnings or errors.';
            $this->setGitHubStatusSuccess(
                gitHubCheckName: self::GITHUB_STATUS_CHECK_NAME,
                gitHubCheckDescription: $checkDescription
            );
        }
        return $result;
    }
}
