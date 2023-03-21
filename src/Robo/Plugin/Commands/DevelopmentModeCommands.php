<?php

namespace Usher\Robo\Plugin\Commands;

use AsyncAws\S3\S3Client;
use DrupalFinder\DrupalFinder;
use Robo\Exception\TaskException;
use Robo\Result;
use Robo\Robo;
use Robo\Tasks;
use Symfony\Component\Yaml\Yaml;
use Usher\Robo\Plugin\Traits\SitesConfigTrait;

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
     * @option skip-lando-start
     *   Skip starting Lando.
     *
     * @aliases magic
     *
     * @return \Robo\Result
     *   The result of the set of tasks.
     */
    public function devRefresh(string $siteName = 'default', array $options = ['skip-lando-start' => false]): Result
    {
        return $this->devRefreshDrupal($siteName, $options['skip-lando-start']);
    }

    /**
     * Refreshes development environments for *all* sites.
     *
     * Completely refreshes a development environment including running 'composer install', starting Lando, downloading
     * a database dump, importing it, running 'drush deploy', disabling front-end caches, and providing a login link.
     *
     * Examples:
     *   dev:refresh-all --skip-sites=common,example --skip-lando-start
     *
     * @option skip-sites
     *   A comma separated list of sites to skip.
     * @option skip-lando-start
     *   Skip starting Lando.
     *
     * @return \Robo\Result
     *   The result of the set of tasks.
     */
    public function devRefreshAll(array $options = ['skip-sites' => '', 'skip-lando-start' => false]): Result
    {
        $skipLandoStart = $options['skip-lando-start'];
        $siteNames = $this->getAllSiteNames();
        $result = null;
        foreach ($siteNames as $siteName) {
            if (in_array($siteName, explode(',', $options['skip-sites']), true)) {
                continue;
            }
            $result = $this->devRefreshDrupal($siteName, $skipLandoStart);
        }
        return $result;
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
