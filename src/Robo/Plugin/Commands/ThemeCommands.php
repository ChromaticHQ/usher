<?php

namespace ChqRobo\Robo\Plugin\Commands;

use ChqRobo\Robo\Plugin\Traits\SitesConfigTrait;
use Robo\Exception\TaskException;
use Robo\Result;
use Robo\Robo;
use Robo\Tasks;

/**
 * Robo commands related to theme operations.
 */
class ThemeCommands extends Tasks
{
    use SitesConfigTrait;

    /**
     * RoboFile constructor.
     */
    public function __construct()
    {
        // Treat this command like bash -e and exit as soon as there's a failure.
        $this->stopOnFail();
    }

    /**
     * Build one or more themes.
     *
     * Configure theme build command(s) to be run using the 'theme_build' key
     * in your .sites.config.yml file.
     *
     * @return \Robo\Result
     *   The result of the set of tasks.
     */
    public function themeBuild($siteName = 'default'): Result
    {
        $themeBuildConfiguration = $this->themeBuildConfiguration($siteName);
        $this->io()->title("theme build");
        foreach ($themeBuildConfiguration as $themeConfig) {
            $themePath = $themeConfig['theme_path'];
            $this->io()->section("building theme at $themePath");
            foreach ($themeConfig['theme_build_commands'] as $themeBuildCommand) {
                $result = $this->taskExec($themeBuildCommand)
                    ->dir($themePath)
                    ->run();
            }
        }
        return $result;
    }

    /**
     * Get theme build configuration with fallback.
     *
     * Retrieves theme_build configuration for a specified site. If the theme_build config is not
     * present for the specified site, fall back to theme_build for 'default' if that is present.
     *
     * @return array
     *   The theme_build configuration array.
     */
    protected function themeBuildConfiguration($siteName = 'default'): array
    {
        $siteConfig = $this->getSiteConfig($siteName);
        $themeBuildConfiguration = $siteConfig['theme_build'] ?? null;
        // @todo: Re-evaluate this fallback code. It's gross. All it does is reduce duplication
        // in the sites config file. Is that worth this complexity?
        if (empty($themeBuildConfiguration)) {
            $defautConfig = $this->getSiteConfig('default');
            $themeBuildConfiguration = $defautConfig['theme_build'] ?? null;
            if (empty($themeBuildConfiguration)) {
                throw new TaskException($this, "Expected theme configuration not present for $siteName.");
            }
            foreach($themeBuildConfiguration as $key => $steps) {
                $placeholder = Robo::config()->get('sitename_placeholder');
                if (strpos($steps['theme_path'], $placeholder) !== false) {
                    $themeBuildConfiguration[$key]['theme_path'] = str_replace($placeholder, $siteName, $themeBuildConfiguration[$key]['theme_path']);
                }
            }
            $this->say("Custom theme_build configuration not present for $siteName.
            Falling back to configuration found in 'default'.");
        }
        return $themeBuildConfiguration;
    }
}
