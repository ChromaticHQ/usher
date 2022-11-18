<?php

namespace Usher\Robo\Plugin\Commands;

use Robo\Result;
use Robo\Tasks;
use Usher\Robo\Plugin\Traits\SlackNotifierTrait;

/**
 * Robo commands related to continuous integration.
 */
class DeployCommands extends Tasks
{
    use SlackNotifierTrait;

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
     *     - 'notify': Force the notification to Slack.
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
        array $opts = ['tugboat' => false, 'notify' => false]
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
        // Attempt to notify Slack if the "tugboat" option is supplied.
        if ($opts['tugboat']) {
            $this->notifySlackOnFailedBasePreviewBuild($result, $opts['notify']);
        }
        return $result;
    }
}
