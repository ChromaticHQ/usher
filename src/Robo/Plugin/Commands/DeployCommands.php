<?php

namespace Usher\Robo\Plugin\Commands;

use Robo\Result;
use Robo\Tasks;
use Usher\Robo\Plugin\Traits\ResultCheckTrait;
use Usher\Robo\Plugin\Traits\SlackNotifierTrait;

/**
 * Robo commands related to continuous integration.
 */
class DeployCommands extends Tasks
{
    use SlackNotifierTrait;

    public const TUGBOAT_DASHBOARD_URL = 'https://dashboard.tugboatqa.com';

    /**
     * RoboFile constructor.
     */
    public function __construct()
    {
        // Treat this command like bash -e and exit as soon as there's a failure.
        $this->stopOnFail();
    }

    /**
     * Run a Drupal 7 deployment.
     *
     * @param string $appDirPath
     *   The app directory path.
     * @param string $siteName
     *   The Drupal site shortname. Optional.
     * @param string $docroot
     *   The Drupal document root directory. Optional.
     *
     * @return \Robo\Result
     *   The result of the set of tasks.
     */
    public function deployDrupal7(string $appDirPath, string $siteName = 'default', string $docroot = 'web'): Result
    {
        return $this->taskExecStack()
            ->dir("$docroot/sites/$siteName")
            ->exec("$appDirPath/vendor/bin/drush cc all")
            ->exec("$appDirPath/vendor/bin/drush updb --yes")
            ->exec("$appDirPath/vendor/bin/drush fra --yes")
            ->exec("$appDirPath/vendor/bin/drush cc all")
            ->run();
    }

    /**
     * Run a Drupal 8/9 deployment.
     *
     * @param string $appDirPath
     *   The app directory path.
     * @param string $siteName
     *   The Drupal site shortname. Optional.
     * @param string $docroot
     *   The Drupal document root directory. Optional.
     * @param array $opts
     *   The array of command line options.
     *     - 'tugboat': Run additional Tugboat specific logic.
     *
     * @aliases deployd
     *
     * @return \Robo\Result
     *   The result of the set of tasks.
     */
    public function deployDrupal(
        string $appDirPath,
        string $siteName = 'default',
        string $docroot = 'web',
        array $opts = ['tugboat' => false]
    ): Result {
        $result = $this->taskExecStack()
            ->dir("$appDirPath/$docroot/sites/$siteName")
            ->exec("$appDirPath/vendor/bin/drush deploy --yes")
            // Import the latest configuration again. This includes the latest
            // configuration_split configuration. Importing this twice ensures that
            // the latter command enables and disables modules based upon the most up
            // to date configuration. Additional information and discussion can be
            // found here:
            // https://github.com/drush-ops/drush/issues/2449#issuecomment-708655673
            ->exec("$appDirPath/vendor/bin/drush config:import --yes")
            ->run();
        // @todo Do we want/need this option if we can just check for environment variables?
        // @todo This could be useful if a site wanted to opt out of this functionality.
        // @todo Perhaps we should rename this option then to be more generic?
        if ($opts['tugboat']) {
            $this->notifySlackOnFailedBasePreviewBuild($result);
        }
        return $result;
    }

    /**
     * Notify Slack if a base preview build failed.
     *
     * @param \Robo\Result $result
     *   The result of the task to check.
     *
     * @see https://docs.tugboatqa.com/starter-configs/code-snippets/slack-integration/
     */
    protected function notifySlackOnFailedBasePreviewBuild(Result $result): void
    {
        // Confirm we are in a Tugboat environment.
        if (getenv('TUGBOAT_PREVIEW_ID') === false) {
            $this->say('Skipping Slack notification since no Tugboat preview ID was found.');
            return;
        }
        // Determine if we are building a base preview.
        // @todo Remove the testing override condition at the end before merging.
        if (getenv('TUGBOAT_PREVIEW_ID') !== getenv('TUGBOAT_BASE_PREVIEW_ID') && getenv('TUGBOAT_PREVIEW_ID') === '') {
            $this->say('Skipping Slack notification since this is not a base preview.');
            return;
        }
        // If everything went well there is nothing to do.
        if ($result->wasSuccessful()) {
            $this->say('Skipping Slack notification since all tasks completed successfully.');
            return;
        }
        // Build various variables and URLs for the Slack message.
        $dashboard_url = sprintf(
            '%s/%s',
            self::TUGBOAT_DASHBOARD_URL,
            getenv('TUGBOAT_PREVIEW_ID'),
        );
        $text = sprintf(
            "Tugboat URL: %s\nDashboard: %s",
            getenv('TUGBOAT_SERVICE_URL'),
            $dashboard_url
        );
        $this->notifySlack('Tugboat', $text);
    }
}
