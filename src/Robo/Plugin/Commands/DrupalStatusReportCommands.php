<?php

namespace Usher\Robo\Plugin\Commands;

use DrupalFinder\DrupalFinder;
use Robo\Exception\TaskException;
use Robo\Robo;
use Robo\Tasks;
use Usher\Robo\Plugin\Traits\GitHubStatusTrait;

/**
 * Robo commands to work with Drupal's status report.
 */
class DrupalStatusReportCommands extends Tasks
{
    use GitHubStatusTrait;

    /**
     * Drupal root directory.
     *
     * @var string
     */
    protected $drupalRoot;

    /**
     * The name of the GitHub status check, if set.
     *
     * @var string
     */
    // @TODO: switch to constant.
    protected $gitHubStatusCheckName = 'ci/drupal-status-report';

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
        $siteDirs = 'default',
        $severity = 1,
        array $options = ['set-pr-status' => false]
    ): void {
        if ($options['set-pr-status']) {
            $this->setGitHubStatusPending($this->gitHubStatusCheckName);
        }
        $sites = explode(',', $siteDirs);
        foreach ($sites as $siteDir) {
            $cmd = [
                "$this->drupalRoot/../vendor/bin/drush",
                "status-report",
                "--format=json",
                "--severity=$severity",
            ];
            if (is_array($ignoreArray = Robo::config()->get('drupal_status_report_ignore_checks'))) {
                $ignoreList = implode(',', $ignoreArray);
                $cmd[] = "--ignore=$ignoreList";
            }
            $result = $this->taskExec(implode(' ', $cmd))
                ->dir("$this->drupalRoot/sites/$siteDir/")
                ->printOutput(false)
                ->run();
            $drushOutput = trim($result->getOutputData());
            $reportJson = json_decode($drushOutput);
            if (!is_array($reportJson) || count($reportJson) > 0) {
                $this->say($drushOutput);
                if ($options['set-pr-status']) {
                    $this->setGitHubStatusError(
                        $this->gitHubStatusCheckName,
                        'Drupal status report shows one or more unexpected warnings or errors.'
                    );
                }
                throw new TaskException(
                    $this,
                    'Drupal status report shows one or more unexpected warnings or errors!'
                );
            }
        }

        $this->say('Drupal status report(s) show no unexpected warnings or errors.');
        if ($options['set-pr-status']) {
            $checkDescription = 'Drupal status report shows no unexpected warnings or errors.';
            $this->setGitHubStatusSuccess($this->gitHubStatusCheckName, $checkDescription);
        }
    }
}
