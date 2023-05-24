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
     * @param array $options
     *   The options.
     *
     * @option bool $notify-slack Default to false. If true, Slack
     *   notification will be sent on build failure in Tugboat.
     * @option bool $notify-slack-force Default to false. If true, it will force
     *   an attempt to notify Slack about the build regardless of what happened.
     *
     * @aliases deployd
     */
    public function deployDrupal(
        string $appDirPath,
        string $siteName = 'default',
        string $docroot = 'web',
        array $options = ['notify-slack' => false, 'notify-slack-force' => false],
    ): Result {
        $result = $this->taskExecStack()
            ->dir("$appDirPath/$docroot/sites/$siteName")
            ->exec("$appDirPath/vendor/bin/drush deploy --yes")
            // Import the latest configuration again. This includes the latest
            // configuration_split configuration. Importing this twice ensures
            // that the latter command enables and disables modules based upon
            // the most up-to-date configuration. Additional information and
            // discussion can be found here:
            // https://github.com/drush-ops/drush/issues/2449#issuecomment-708655673
            ->exec("$appDirPath/vendor/bin/drush config:import --yes")
            ->run();
        ['notify-slack' => $notifySlack, 'notify-slack-force' => $notifySlackForce] = $options;
        // Notify Slack about the failed deployment if the option was provided.
        if ($notifySlack || $notifySlackForce) {
            $this->notifySlackOnFailedBasePreviewBuild($result, $notifySlackForce);
        }
        return $result;
    }
}
