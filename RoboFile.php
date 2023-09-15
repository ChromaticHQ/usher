<?php

/**
 * This is project's console commands configuration for Robo task runner.
 *
 * @see http://robo.li/
 */

use Robo\Tasks;

/**
 * Robo commands available to developers.
 */
class RoboFile extends Tasks {
    use Usher\Robo\Task\Discovery\Tasks;
}
