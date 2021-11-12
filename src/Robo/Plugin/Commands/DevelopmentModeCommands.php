<?php

namespace ChqRobo\Robo\Plugin\Commands;

use AsyncAws\S3\S3Client;
use ChqRobo\Robo\Plugin\Traits\SitesConfigTrait;
use DrupalFinder\DrupalFinder;
use Robo\Exception\TaskException;
use Robo\Result;
use Robo\Robo;
use Robo\Tasks;
use Symfony\Component\Yaml\Yaml;

/**
 * Robo commands related to changing development modes.
 */
class DevelopmentModeCommands extends DevelopmentModeBaseCommands
{

    /**
     * Refreshes a development environment.
     *
     * Completely refreshes a development environment including running 'composer install', starting Lando, downloading
     * a database dump, importing it, running 'drush deploy', disabling front-end caches, and providing a login link.
     *
     * @param string $siteName
     *   The Drupal site name.
     *
     * @aliases magic
     *
     * @return \Robo\Result
     *   The result of the set of tasks.
     */
    public function devRefresh(string $siteName = 'default'): Result
    {
        return $this->devRefreshDrupal($siteName);
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
    public function frontendDevEnable(string $siteDir = 'default', array $opts = ['yes|y' => false])
    {
        return $this->frontendDevEnableDrupal($siteDir, $opts);
    }
}
