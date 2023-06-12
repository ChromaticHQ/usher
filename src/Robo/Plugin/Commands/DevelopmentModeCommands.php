<?php

namespace Usher\Robo\Plugin\Commands;

use AsyncAws\S3\S3Client;
use DrupalFinder\DrupalFinder;
use Robo\Exception\TaskException;
use Robo\Result;
use Robo\Robo;
use Robo\Tasks;
use Symfony\Component\Yaml\Yaml;
use Usher\Robo\Plugin\Enums\LocalDevEnvironmentTypes;
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
     * @param string $environmentType
     *   Specify local development environment: ddev, lando.
     * @param string $siteName
     *   The Drupal site name.
     * @option start-local-dev
     *   Skip starting Lando.
     *
     * @aliases magic
     */
    public function devRefresh(
        string $environmentType,
        string $siteName = 'default',
        array $options = ['start-local-dev' => false],
    ): Result {
        return $this->devRefreshDrupal(
            LocalDevEnvironmentTypes::from($environmentType),
            $siteName,
            $options['start-local-dev'],
        );
    }

    /**
     * Refreshes development environments for *all* sites.
     *
     * Completely refreshes a development environment including running 'composer install', starting Lando, downloading
     * a database dump, importing it, running 'drush deploy', disabling front-end caches, and providing a login link.
     *
     * Examples:
     *   dev:refresh-all ddev --skip-sites=common,example
     *
     * @param string $environmentType
     *   Specify local development environment: ddev, lando.
     * @option skip-sites
     *   A comma separated list of sites to skip.
     * @option start-local-dev
     *   Start local development environment.
     */
    public function devRefreshAll(
        string $environmentType,
        array $options = ['skip-sites' => '', 'start-local-dev' => false]
    ): Result {
        ['skip-sites' => $skipSites, 'start-local-dev' => $startLocalDev] = $options;
        $siteNames = $this->getAllSiteNames();
        $result = null;
        foreach ($siteNames as $siteName) {
            if (in_array($siteName, explode(separator: ',', string: (string) $skipSites), true)) {
                continue;
            }
            $result = $this->devRefreshDrupal(
                LocalDevEnvironmentTypes::from($environmentType),
                $siteName,
                $startLocalDev,
            );
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
