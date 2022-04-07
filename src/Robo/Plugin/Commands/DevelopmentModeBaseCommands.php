<?php

namespace Usher\Robo\Plugin\Commands;

use DrupalFinder\DrupalFinder;
use Robo\Exception\TaskException;
use Robo\Result;
use Robo\Robo;
use Robo\Tasks;
use Symfony\Component\Yaml\Yaml;
use Usher\Robo\Plugin\Traits\DatabaseDownloadTrait;
use Usher\Robo\Plugin\Traits\SitesConfigTrait;

/**
 * Robo commands related to changing development modes.
 *
 * Provides base functionality with the assumption of Drupal 8 or above.
 */
class DevelopmentModeBaseCommands extends Tasks
{
    use DatabaseDownloadTrait;
    use SitesConfigTrait;

    /**
     * Drupal root directory.
     *
     * @var string
     */
    protected $drupalRoot;

    /**
     * Composer vendor directory.
     *
     * @var string
     */
    protected $vendorDirectory;

    /**
     * Path to front-end development services path.
     *
     * @var string
     */
    protected $devServicesPath;

    /**
     * Class constructor.
     */
    public function __construct()
    {
        // Treat this command like bash -e and exit as soon as there's a failure.
        $this->stopOnFail();

        // Find Drupal root path.
        $drupalFinder = new DrupalFinder();
        $drupalFinder->locateRoot(getcwd());
        $this->drupalRoot = $drupalFinder->getDrupalRoot();
        $this->vendorDirectory = $drupalFinder->getVendorDir();
        $this->devServicesPath = "$this->drupalRoot/sites/fe.development.services.yml";
    }

    /**
     * Refresh a site database in Lando.
     *
     * @param string $siteName
     *   The Drupal site name.
     *
     * @return \Robo\Result
     *   The result of the set of tasks.
     */
    public function databaseRefreshLando(string $siteName = 'default'): Result
    {
        $this->io()->title('lando database refresh.');

        $dbPath = $this->databaseDownload($siteName);

        $this->io()->section("importing $siteName database.");
        $this->say("Importing $dbPath");
        // If this is a multi-site, include a host option so Lando imports to the correct database.
        $hostOption = $siteName !== 'default' ? "--host=$siteName" : '';
        $this->taskExec('lando')
            ->arg('db-import')
            ->arg($dbPath)
            ->arg($hostOption)
            ->run();

        $this->say("Deleting $dbPath");
        $this->taskExec('rm')->args($dbPath)->run();
        return $this->drushDeployLando($siteName);
    }

    /**
     * Refresh database on Tugboat.
     *
     * @return \Robo\Result
     *   The result of the set of tasks.
     */
    public function databaseRefreshTugboat(): Result
    {
        $this->io()->title('refresh tugboat databases.');
        $result = null;
        foreach ($this->getAllSitesConfig() as $siteName => $siteInfo) {
            try {
                $dbPath = $this->databaseDownload($siteName);
            } catch (TaskException $e) {
                $this->yell("$siteName: No database configured. Download/import skipped.");
                // @todo: Should we run a site-install by default?
                continue;
            }
            if (!is_string($dbPath) || strlen($dbPath) == 0) {
                $this->yell("'$siteName' database path not found.");
                continue;
            }
            $dbName = $siteName === 'default' ? 'tugboat' : $siteName;
            $result = $this->taskExec('mysql')
                ->option('-h', 'mariadb')
                ->option('-u', 'tugboat')
                ->option('-ptugboat')
                ->option('-e', "drop database if exists $dbName; create database $dbName;")
                ->run();

            $this->io()->section("import $siteName database.");
            $result = $this->taskExec("zcat $dbPath | mysql -h mariadb -u tugboat -ptugboat $dbName")
                ->run();
            $result = $this->taskExec('rm')->args($dbPath)->run();
        }
        return $result;
    }

    /**
     * Generate Drupal login link.
     *
     * @command drupal:login-link
     * @aliases uli
     *
     * @param string $siteDir
     *   The Drupal site directory name.
     * @param array $options
     *   Array of options as described below.
     *
     * @option lando Whether to run the automatic fixer or not.
     *
     * @return \Robo\Result
     *   The result of the set of tasks.
     */
    public function drupalLoginLink($siteDir = 'default', array $options = ['lando' => true]): Result
    {
        $this->io()->section("create login link.");
        if ($options['lando']) {
            $uri = $this->landoUri($siteDir);
            $this->say("Lando URI detected: $uri");
            return $this->taskExec('lando')
                ->arg('drush')
                ->arg('user:login')
                ->option('--uri', $uri)
                ->dir("$this->drupalRoot/sites/$siteDir")
                ->run();
        }
        return $this->taskExec("$this->vendorDirectory/bin/drush")
            ->arg('user:login')
            ->dir("$this->drupalRoot/sites/$siteDir")
            ->run();
    }

    /**
     * Disable front-end development mode.
     *
     * @param string $siteDir
     *   The Drupal site directory name.
     * @param array $opts
     *   The options.
     *
     * @option boolean $yes Default answers to yes.
     * @aliases fedd
     *
     * @return \Robo\Result
     *   The result of the set of tasks.
     */
    public function frontendDevDisable($siteDir = 'default', array $opts = ['yes|y' => false])
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
        $this->io()->title('disabling front-end development mode.');
        // https://github.com/consolidation/robo/issues/1059#issuecomment-967732068
        // @phpstan-ignore-next-line
        return $this->collectionBuilder()
            ->taskFilesystemStack()
            ->remove($devSettingsPath)
            ->remove($this->devServicesPath)
            ->run();
    }

    /**
     * Refreshes a development environment based upon the Drupal version.
     *
     * @param string $siteName
     *   The Drupal site name.
     *
     * @return \Robo\Result
     *   The result of the set of tasks.
     */
    protected function devRefreshDrupal(string $siteName = 'default'): Result
    {
        $this->io()->title('development environment refresh. 🦄✨');
        $result = $this->taskComposerInstall()->run();
        $result = $this->taskExec('lando')->arg('start')->run();
        // There isn't a great way to call a command in one class from another.
        // https://github.com/consolidation/Robo/issues/743
        // For now, it seems like calling robo from within robo works.
        $result = $this->taskExec("composer robo theme:build $siteName")
            ->run();
        $result = $this->frontendDevEnableDrupal($siteName, ['yes' => true]);
        $result = $this->databaseRefreshLando($siteName);
        $result = $this->drupalLoginLink($siteName);
        return $result;
    }

    /**
     * Detect Lando URI.
     *
     * @param string $siteDir
     *   The Drupal site directory name.
     *
     * @return string
     *   The Lando URI.
     */
    protected function landoUri($siteDir): string
    {
        try {
            $landoCfg = Yaml::parseFile($landoConfigPath);
        } catch (ParseException $exception) {
            // This site could have a Front- and Back-end site in different
            // sub-directories with a the lando.yml in the root directory.
            $landoConfigPath = "$this->drupalRoot/../../.lando.yml";
            $landoCfg = Yaml::parseFile($landoConfigPath);
        }
        // First, check for multisite proxy configuration.
        if (isset($landoCfg['proxy']['appserver'])) {
            if ($siteDir === 'default') {
                throw new TaskException(
                    $this,
                    'Unable to determine URI. Multi-site detected, but you did not specify a site name.',
                );
            }
            // Detect multi-site configurations.
            // We look for the $siteDir to be at the beginning of the appserver
            // proxy URL.
            $siteDomains = array_filter($landoCfg['proxy']['appserver'], fn($domain) =>
                strpos($domain, $siteDir) === 0);
            if (count($siteDomains) > 1) {
                $this->say('More than one possible URI found in Lando config >>> ' . implode(' | ', $siteDomains));
            } elseif (count($siteDomains) == 1) {
                $domain = array_pop($siteDomains);
                return "http://$domain";
            }
        } elseif (isset($landoCfg['services']['appserver']['overrides']['environment']['DRUSH_OPTIONS_URI'])) {
            // If a Drush URI is explicitly set, use that.
            return $landoCfg['services']['appserver']['overrides']['environment']['DRUSH_OPTIONS_URI'];
        } else {
            // Our final fallback.
            return 'http://' . $landoCfg['name'] . '.' . 'lndo.site';
        }
        throw new TaskException($this, 'Unable to determine URI.');
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
        $this->io()->section('drush deploy.');
        if (!class_exists('Drush\Commands\core\DeployCommands')) {
            throw new TaskException(
                $this,
                "'drush deploy' command not found. Further work is necessary to support this version of Drush."
            );
        }
        return $this->taskExecStack()
            ->dir("$this->drupalRoot/sites/$siteDir")
            ->exec("lando drush deploy --yes")
            // Import the latest configuration again. This includes the latest
            // configuration_split configuration. Importing this twice ensures that
            // the latter command enables and disables modules based upon the most up
            // to date configuration. Additional information and discussion can be
            // found here:
            // https://github.com/drush-ops/drush/issues/2449#issuecomment-708655673
            ->exec("lando drush config:import --yes")
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
        $this->say("copying settings.local.php and development.services.yml into sites/$siteDir.");

        // Copy the example local settings file.
        $example_local_settings_file = "$this->drupalRoot/sites/example.settings.local.php";
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
        // Copy the development services file.
        $development_services_file = "$this->drupalRoot/sites/development.services.yml";
        if (file_exists($development_services_file)) {
            $result = $this->taskFilesystemStack()
                ->copy($development_services_file, $this->devServicesPath, true)
                ->run();
        } else {
            throw new TaskException(
                $this,
                "The \"$development_services_file\" file was not found."
            );
        }

        $this->say("enablig twig.debug in development.services.yml.");
        $devServices = Yaml::parseFile($this->devServicesPath);
        $devServices['parameters']['twig.config'] = [
            'debug' => true,
            'auto_reload' => true,
        ];
        file_put_contents($this->devServicesPath, Yaml::dump($devServices));
        $this->say("disabling render and dynamic_page_cache in settings.local.php.");
        // https://github.com/consolidation/robo/issues/1059#issuecomment-967732068
        // @phpstan-ignore-next-line
        $result = $this->collectionBuilder()
            ->taskReplaceInFile($devSettingsPath)
            ->from('/sites/development.services.yml')
            ->to("/sites/fe.development.services.yml")
            ->taskReplaceInFile($devSettingsPath)
            ->from('# $settings[\'cache\'][\'bins\'][\'render\']')
            ->to('$settings[\'cache\'][\'bins\'][\'render\']')
            ->taskReplaceInFile($devSettingsPath)
            ->from('# $settings[\'cache\'][\'bins\'][\'dynamic_page_cache\'] = ')
            ->to('$settings[\'cache\'][\'bins\'][\'dynamic_page_cache\'] = ')
            ->taskReplaceInFile($devSettingsPath)
            ->from('# $settings[\'cache\'][\'bins\'][\'page\'] = ')
            ->to('$settings[\'cache\'][\'bins\'][\'page\'] = ')
            ->taskWriteToFile($devSettingsPath)
            ->append(true)
            ->line('')
            ->line('/**')
            ->line(' *  If advagg module is present, disable its functionality.')
            ->line(' */')
            ->line('$config[\'advagg.settings\'][\'enabled\'] = FALSE;')
            ->run();
        return $result;
    }
}
