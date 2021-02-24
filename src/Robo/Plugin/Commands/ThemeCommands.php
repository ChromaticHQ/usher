<?php

namespace ChqRobo\Robo\Plugin\Commands;

use Robo\Exception\TaskException;
use Robo\Result;
use Robo\Robo;
use Robo\Tasks;

/**
 * Robo commands related to theme operations.
 */
class ThemeCommands extends Tasks
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
     * Build a theme.
     *
     * @return \Robo\Result
     *   The result of the set of tasks.
     */
    public function themeBuild(): Result
    {
        if (empty($themeBuildConfiguration = Robo::config()->get('theme_build'))) {
            throw new TaskException($this, 'Expected theme configuration not present: theme_build');
        }
        $this->io()->title("building themes");
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
