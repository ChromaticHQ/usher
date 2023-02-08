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
     * @param string $siteDir
     *   The Drupal site directory.
     * @param int $severity
     *   The minimum severity level to show. Defaults to 1 (warning).
     *
     * @throws \Robo\Exception\TaskException
     */
    public function drupalStatusReport(
        $siteDir = 'default',
        $severity = 1,
        array $options = ['set-pr-status' => false]
    ): void {
        if ($options['set-pr-status']) {
            $this->setGitHubStatusPending($this->gitHubStatusCheckName);
        }
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
                $this->setGitHubStatusError($this->gitHubStatusCheckName);
            }
            throw new TaskException(
                $this,
                'Drupal status report shows one or more unexpected warnings or errors!'
            );
        }
        $this->say('Drupal status report shows no unexpected warnings or errors.');
        if ($options['set-pr-status']) {
            $this->setGitHubStatusSuccess($this->gitHubStatusCheckName);
        }
    }
}
