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
        ['skip-sites' => $skipSites, 'skip-lando-start' => $skipLandoStart] = $options;
        $siteNames = $this->getAllSiteNames();
        $result = null;
        foreach ($siteNames as $siteName) {
            if (in_array($siteName, explode(',', $skipSites), true)) {
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

    /**
     * Setup a site in GitHub Codespaces.
     *
     * @return \Robo\Result
     *   The result of the set of tasks.
     *
     * @throws \Robo\Exception\TaskException
     *
     */
    public function setupCodespaces(): Result
    {
        $this->io()->title('Symlinking file system.');
        $docRootDir = Robo::config()->get('drupal_document_root') ?? 'web';
        $codespaces_directory = getenv('PWD') . '/' . $docRootDir;
        if (empty($codespaces_directory)) {
            throw new TaskException($this, 'Codespaces directory is unavailable.');
        }
        $this->taskDeleteDir('/var/www/html')->run();
        $result = $this->taskExec("ln -s $codespaces_directory /var/www/html")->run();

        $this->io()->title('Start apache, forwarding port 80.');
        $result = $this->taskExec('service apache2 start')->run();

        $this->io()->title('Download database.');
        $dbPath = $this->databaseDownload();
        if (empty($dbPath)) {
            throw new TaskException($this, 'Database download failed.');
        }

        $this->io()->section('Importing database.');
        $result = $this->taskExec("zcat $dbPath | mysql -h db -u mariadb -pmariadb mariadb")->run();
        $result = $this->taskExec('rm')->args($dbPath)->run();

        $this->io()->section('Building theme.');
        $result = $this->taskExec('composer robo theme:build')->run();

        $this->io()->section('Drush deploy.');
        $result = $this->taskExecStack()
            ->exec("vendor/bin/drush deploy --yes")
            // Import the latest configuration again. This includes the latest
            // configuration_split configuration. Importing this twice ensures that
            // the latter command enables and disables modules based upon the most up
            // to date configuration. Additional information and discussion can be
            // found here:
            // https://github.com/drush-ops/drush/issues/2449#issuecomment-708655673
            ->exec("drush config:import --yes")
            ->run();
        return $result;
    }
}
