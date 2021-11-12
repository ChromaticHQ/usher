<?php

namespace ChqRobo\Robo\Plugin\Commands;

use Robo\Exception\TaskException;
use Robo\Result;

/**
 * Robo commands related to changing development modes for Drupal 7.
 */
class LegacyDevelopmentModeCommands extends DevelopmentModeCommands
{
    /**
     * Refreshes a Drupal 7 development environment.
     *
     * Completely refreshes a development environment including running 'composer install', starting Lando,
     * downloading a database dump, importing it, running deployment commands, disabling front-end caches,
     * and providing a login link.
     *
     * @param string $siteName
     *   The Drupal site name.
     *
     * @aliases magic
     *
     * @return \Robo\Result
     *   The result of the set of tasks.
     */
    public function devRefreshLegacy($siteName = 'default'): Result
    {
        $this->io()->title('development environment refresh. 🦄✨');
        $result = $this->taskComposerInstall()->run();
        // $result = $this->taskExec('lando')->arg('start')->run();
        // There isn't a great way to call a command in one class from another.
        // https://github.com/consolidation/Robo/issues/743
        // For now, it seems like calling robo from within robo works.
        $result = $this->taskExec("composer robo theme:build $siteName")
            ->run();
        $result = $this->frontendDevEnableDrupal7($siteName, ['yes' => true]);
        $result = $this->databaseRefreshLando($siteName);
        $result = $this->drupalLoginLink($siteName);
        return $result;
    }

    /**
     * Deploy with Drush via Lando.
     *
     * @param string $siteDir
     *   The Drupal site directory name.
     *
     * @return \Robo\Result
     *   The result of the set of tasks.
     *
     * @see https://www.drush.org/deploycommand
     */
    protected function drushDeployLando($siteDir = 'default'): Result
    {
        $this->io()->section('drush updatedb & drush cach-clear.');
        return $this->taskExecStack()
            ->dir("$this->drupalRoot/sites/$siteDir")
            ->exec("lando drush cache-clear all")
            ->exec("lando drush updatedb --yes")
            ->exec("lando drush fra --yes")
            ->exec("lando drush cache-clear all")
            ->run();
    }

    /**
     * Enable front-end development mode.
     *
     * @param string $siteDir
     *   The Drupal site directory name.
     * @param array $opts
     *   The options.
     *
     * @option boolean $yes Default answers to yes.
     * @aliases fede
     *
     * @return \Robo\Result
     *   The result of the set of tasks.
     */
    public function frontendDevEnableDrupal7($siteDir = 'default', array $opts = ['yes|y' => false])
    {
        $devSettingsPath = "$this->drupalRoot/sites/$siteDir/settings.local.php";

        if (!$opts['yes']) {
            $this->yell("This command will overwrite any customizations you have made to $devSettingsPath and
                $this->devServicesPath.");
            $yes = $this->io()->confirm('This command is destructive. Do you wish to continue?');
            if (!$yes) {
                return Result::cancelled();
            }
        }

        $this->io()->title('enabling front-end development mode.');
        $this->say("copying settings.local.php into sites/$siteDir.");

        // Copy the example local settings file.
        $example_local_settings_file = "$this->drupalRoot/sites/example.settings.local.php";
        $this->say($example_local_settings_file);
        $this->say('root: ' . $this->drupalRoot);
        if (file_exists($example_local_settings_file)) {
            $result = $this->taskFilesystemStack()
                ->copy($example_local_settings_file, $devSettingsPath)
                ->run();
        } else {
            throw new TaskException(
                $this,
                "The \"$example_local_settings_file\" file was not found."
            );
        }

        return $result;
    }
}
