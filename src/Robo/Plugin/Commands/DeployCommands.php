<?php

namespace CHQRobo\Robo\Plugin\Commands;

use Robo\Exception\TaskException;
use Robo\Result;
use Robo\Robo;
use Robo\Tasks;

/**
 * Robo commands related to continuous integration.
 */
class DeployCommands extends Tasks
{
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
     *   The drupal document root directory. Optional.
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
     *
     * @return \Robo\Result
     *   The result of the set of tasks.
     */
    public function deployDrupal(string $appDirPath, string $siteName = 'default', string $docroot = 'web'): Result
    {
        return $this->taskExecStack()
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
    }
}
