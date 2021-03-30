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
        $themeBuildConfiguration = $this->getConfig('theme_build', $siteName);
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
}
