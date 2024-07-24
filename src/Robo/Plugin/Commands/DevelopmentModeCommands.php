<?php

namespace Usher\Robo\Plugin\Commands;

use DrupalFinder\DrupalFinderComposerRuntime;
use Robo\Exception\TaskException;
use Robo\Result;
use Robo\ResultData;
use Robo\Tasks;
use Symfony\Component\Yaml\Yaml;
use Usher\Robo\Plugin\Enums\LocalDevEnvironmentTypes;
use Usher\Robo\Plugin\Traits\DatabaseDownloadTrait;
use Usher\Robo\Plugin\Traits\DrupalVersionTrait;
use Usher\Robo\Plugin\Traits\SitesConfigTrait;
use Usher\Robo\Task\Discovery\Alternatives;

/**
 * Robo commands related to changing development modes.
 */
class DevelopmentModeCommands extends Tasks
{
    use DatabaseDownloadTrait;
    use SitesConfigTrait;
    use DrupalVersionTrait;

    /**
     * Drupal root directory.
     *
     * @var string
     */
    protected string $drupalRoot;

    /**
     * Composer vendor directory.
     *
     * @var string
     */
    protected string $vendorDirectory;

    /**
     * Path to front-end development services path.
     *
     * @var string
     */
    protected string $devServicesPath;

    /**
     * Class constructor.
     */
    public function __construct()
    {
        // Treat this command like bash -e and exit as soon as there's a failure.
        $this->stopOnFail();

        // Find Drupal root path.
        $drupalFinder = new DrupalFinderComposerRuntime();
        $this->drupalRoot = $drupalFinder->getDrupalRoot();
        $this->vendorDirectory = $drupalFinder->getVendorDir();
        $this->devServicesPath = "$this->drupalRoot/sites/fe.development.services.yml";
    }

    /**
     * Refreshes a development environment.
     *
     * Completely refreshes a development environment including running 'composer install', downloading
     * a database dump, importing it, running deployment commands, disabling front-end caches, and
     * providing a login link.
     *
     * @param string $siteName
     *   The Drupal site name.
     * @option db
     *   Provide a database dump instead of relying on the latest available.
     * @option environment-type
     *   Specify alternative (supported) environment type. See LocalDevEnvironmentTypes enum.
     *
     * @aliases magic
     */
    public function devRefresh(
        string $siteName = 'default',
        array $options = ['db' => '', 'environment-type' => 'ddev'],
    ): Result {
        ['db' => $dbPath, 'environment-type' => $environmentType] = $options;
        return $this->devRefreshDrupal(
            environmentType: LocalDevEnvironmentTypes::from($environmentType),
            siteName: $siteName,
            databasePath: $dbPath,
        );
    }

    /**
     * Refreshes development environments for *all* sites.
     *
     * Completely refreshes a development environment including running 'composer install', downloading
     * a database dump, importing it, running deployment commands, disabling front-end caches, and
     * providing a login link.
     *
     * Examples:
     *   dev:refresh-all ddev --skip-sites=common,example
     *
     * @option skip-sites
     *   A comma separated list of sites to skip.
     * @option environment-type
     *   Specify alternative (supported) environment type. See LocalDevEnvironmentTypes enum.
     */
    public function devRefreshAll(
        array $options = ['skip-sites' => '', 'environment-type' => 'ddev']
    ): Result {
        ['skip-sites' => $skipSites, 'environment-type' => $environmentType] = $options;
        $siteNames = $this->getAllSiteNames();
        $result = null;
        foreach ($siteNames as $siteName) {
            if (in_array($siteName, explode(separator: ',', string: (string) $skipSites), true)) {
                continue;
            }
            $result = $this->devRefreshDrupal(
                environmentType: LocalDevEnvironmentTypes::from($environmentType),
                siteName: $siteName,
            );
        }
        return $result;
    }

    /**
     * Refresh a site database in DDEV.
     *
     * @param string $siteName
     *   The Drupal site name.
     * @option db
     *   Provide a path to a database dump to be used instead of downloading the latest dump.
     */
    public function databaseRefreshDdev(string $siteName = 'default', array $options = ['db' => '']): Result
    {
        // @todo: Update this method to not be DDEV specific.
        $this->io()->title('DDEV database refresh.');

        ['db' => $dbPath] = $options;
        // Track whether a database path was provided by the user or not.
        $dbPathProvidedByUser = $dbPath !== '';

        if (!$dbPathProvidedByUser) {
            $dbPath = $this->databaseDownload($siteName);
        }

        $this->io()->section("importing $siteName database.");
        $this->say("Importing $dbPath");
        $this->taskExec(LocalDevEnvironmentTypes::DDEV->value)
            ->arg('import-db')
            ->option('database', $siteName === 'default' ? 'db' : $siteName)
            ->option('file', $dbPath)
            ->run();

        // If a database was downloaded as part of this process, delete it.
        if (!$dbPathProvidedByUser) {
            $this->deleteDatabase($dbPath);
        }

        return $this->drushDeployWith(
            localEnvironmentType: LocalDevEnvironmentTypes::DDEV,
            siteDir: $siteName,
        );
    }

    /**
     * Refresh database on Tugboat.
     */
    public function databaseRefreshTugboat(): ResultData
    {
        $this->io()->title('refresh tugboat databases.');
        $resultData = new ResultData();

        foreach (array_keys($this->getAllSitesConfig()) as $siteName) {
            $dbPath = '';
            try {
                $dbPath = $this->databaseDownload($siteName);
            } catch (TaskException $e) {
                $this->yell("$siteName: No database configured. Download/import skipped.");
                $resultData->append($e->getMessage());
                // @todo: Should we run a site-install by default?
                continue;
            }
            if (!is_string($dbPath) || $dbPath === '') {
                $this->yell("'$siteName' database path not found.");
                $resultData->append("'$siteName' database path not found.");
                continue;
            }
            $dbName = $siteName === 'default' ? 'tugboat' : $siteName;
            $taskResult = $this->task(Alternatives::class, 'mariadb', ['mysql'])->run();
            if (!$taskResult->wasSuccessful()) {
                $resultData->append($taskResult);
                continue;
            }
            $dbDriver = $taskResult->getData()['path'];
            $taskResult = $this->taskExec($dbDriver)
                ->option('-h', 'mariadb')
                ->option('-u', 'tugboat')
                ->option('-ptugboat')
                ->option('-e', "drop database if exists $dbName; create database $dbName;")
                ->run();
            $resultData->append($taskResult);
            $this->io()->section("import $siteName database.");
            $taskResult = $this->taskExec("zcat $dbPath | $dbDriver -h mariadb -u tugboat -ptugboat $dbName")
                ->run();
            $resultData->append($taskResult);
            $taskResult = $this->taskExec('rm')->args($dbPath)->run();
            $resultData->append($taskResult);

            if (!$this->drupalVersionIsD7($this->drupalRoot)) {
                $taskResult = $this->taskExec("$this->vendorDirectory/bin/drush")
                    ->arg('cache:rebuild')
                    ->dir("$this->drupalRoot/sites/$siteName")
                    ->run();
                $resultData->append($taskResult);
            }
        }

        return $resultData;
    }

    /**
     * Generate Drupal login link.
     *
     * @command drupal:login-link
     * @aliases uli
     *
     * @param string $environmentType
     *   Specify local development enviroment: ddev. This value is a string instead of LocalDevEnvironmentTypes since
     *   it is a public command that can be called from the command line.
     * @param string $siteDir
     *   The Drupal site directory name.
     */
    public function drupalLoginLink(
        string $environmentType,
        string $siteDir = 'default',
    ): Result {
        $this->io()->section("create login link.");
        $uid = $this->getDrupalSiteAdminUid(siteName: $siteDir);
        return $this->taskExec($environmentType)
            ->arg('drush')
            ->arg("@$siteDir.$environmentType")
            ->arg('user:login')
            ->option("--uid=$uid")
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
    public function frontendDevDisable(string $siteDir = 'default', array $opts = ['yes|y' => false])
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
        return $this->collectionBuilder()
            ->taskFilesystemStack()
            ->remove($devSettingsPath)
            ->remove($this->devServicesPath)
            ->run();
    }

    /**
     * Refreshes a development environment based upon the Drupal version.
     */
    protected function devRefreshDrupal(
        LocalDevEnvironmentTypes $environmentType,
        string $siteName = 'default',
        string $databasePath = '',
    ): Result {
        $this->io()->title('development environment refresh. 🦄✨');
        $result = $this->taskComposerInstall()->run();

        // There isn't a great way to call a command in one class from another.
        // https://github.com/consolidation/Robo/issues/743
        // For now, it seems like calling robo from within robo works.
        $result = $this->taskExec("composer robo theme:build $siteName")
            ->run();
        $result = $this->frontendDevEnable($siteName, ['yes' => true]);

        $result = $this->databaseRefreshDdev(siteName: $siteName, options: ['db' => $databasePath]);

        return $this->drupalLoginLink($environmentType->value, $siteName);
    }

    /**
     * Deploy with Drush via a local development environment.
     *
     * @see https://www.drush.org/deploycommand
     */
    protected function drushDeployWith(
        LocalDevEnvironmentTypes $localEnvironmentType,
        string $siteDir = 'default',
    ): Result {
        $this->io()->section('drush deploy.');
        if (!class_exists(\Drush\Commands\core\DeployCommands::class)) {
            throw new TaskException(
                $this,
                "'drush deploy' command not found. Further work is necessary to support this version of Drush."
            );
        }
        return $this->taskExecStack()
            ->dir("$this->drupalRoot/sites/$siteDir")
            ->exec("$localEnvironmentType->value drush @$siteDir.$localEnvironmentType->value deploy --yes")
            // Import the latest configuration again. This includes the latest
            // configuration_split configuration. Importing this twice ensures that
            // the latter command enables and disables modules based upon the most up
            // to date configuration. Additional information and discussion can be
            // found here:
            // https://github.com/drush-ops/drush/issues/2449#issuecomment-708655673
            ->exec("$localEnvironmentType->value drush @$siteDir.$localEnvironmentType->value config:import --yes")
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
    public function frontendDevEnable(string $siteDir = 'default', array $opts = ['yes|y' => false])
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

        $this->say("optimizing twig for front-end development in development services yml config.");
        $devServices = Yaml::parseFile($this->devServicesPath);
        $devServices['parameters']['twig.config'] = [
            'debug' => true,
            'auto_reload' => true,
            'cache' => false,
        ];
        file_put_contents($this->devServicesPath, Yaml::dump($devServices));
        $this->say("disabling render and dynamic_page_cache in settings.local.php.");
        // https://github.com/consolidation/robo/issues/1059#issuecomment-967732068
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
