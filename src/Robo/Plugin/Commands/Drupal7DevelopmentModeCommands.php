<?php

namespace Usher\Robo\Plugin\Commands;

use Robo\Exception\TaskException;
use Robo\Result;
use Usher\Robo\Plugin\Enums\LocalDevEnvironmentTypes;

/**
 * Robo commands related to changing development modes for Drupal 7.
 */
class Drupal7DevelopmentModeCommands extends DevelopmentModeBaseCommands
{
    /**
     * Refreshes a Drupal 7 development environment.
     *
     * Completely refreshes a development environment including running 'composer install', starting Lando,
     * downloading a database dump, importing it, running deployment commands, disabling front-end caches,
     * and providing a login link.
     *
     * @param string $environmentType
     *   Specify local development environment: ddev, lando.
     * @param string $siteName
     *   The Drupal site name.
     */
    public function devRefreshDrupal7(string $environmentType, string $siteName = 'default'): Result
    {
        return $this->devRefreshDrupal(LocalDevEnvironmentTypes::from($environmentType), $siteName);
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
     */
    public function frontendDevEnableDrupal7(string $siteDir = 'default', array $opts = ['yes|y' => false]): Result
    {
        return $this->frontendDevEnableDrupal($siteDir, $opts);
    }

    /**
     * {@inheritdoc}
     */
    protected function frontendDevEnableDrupal(string $siteDir = 'default', array $opts = ['yes|y' => false]): Result
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
