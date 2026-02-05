<?php

namespace Usher\Robo\Plugin\Commands;

use DrupalFinder\DrupalFinderComposerRuntime;
use Robo\Exception\TaskException;
use Robo\Result;
use Robo\ResultData;
use Robo\Symfony\ConsoleIO;
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
        ConsoleIO $io,
        string $siteName = 'default',
        array $options = ['db' => '', 'environment-type' => 'ddev'],
    ): Result|ResultData {
        ['db' => $dbPath, 'environment-type' => $environmentType] = $options;
        return $this->devRefreshDrupal(
            io: $io,
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
        ConsoleIO $io,
        array $options = ['skip-sites' => '', 'environment-type' => 'ddev']
    ): Result|ResultData {
        ['skip-sites' => $skipSites, 'environment-type' => $environmentType] = $options;
        $siteNames = $this->getAllSiteNames();
        $result = ResultData::message('No sites were refreshed because all sites were configured to be skipped.');
        foreach ($siteNames as $siteName) {
            if (in_array($siteName, explode(separator: ',', string: (string) $skipSites), true)) {
                continue;
            }
            /** @var Result|ResultData $result */
            $result = $this->devRefreshDrupal(
                $io,
                environmentType: LocalDevEnvironmentTypes::from($environmentType),
                siteName: $siteName,
            );
            if ($result->wasCancelled()) {
                $io->say("Cancelling the refresh for all sites.");
                return $result;
            }
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
     *
     * The data file to be imported must be either a gzipped or non-gzipped sql file.
     */
    public function databaseRefreshDdev(
        ConsoleIO $io,
        string $siteName = 'default',
        array $options = ['db' => '']
    ): Result|ResultData {
        $io->title('DDEV database refresh.');

        ['db' => $dbPath] = $options;
        // Track whether a database path was provided by the user or not.
        $dbPathProvidedByUser = $dbPath !== '';

        if (!$dbPathProvidedByUser) {
            try {
                $dbPath = $this->databaseDownload($io, $siteName);
            } catch (TaskException $e) {
                $resultData = new ResultData(ResultData::EXITCODE_ERROR, $e->getMessage());
                $io->yell("$siteName: No database configured. Download/import skipped.");
                // @todo: Should we run a site-install by default? The "common"
                // site ends up here, but we could change that.
                return $resultData;
            }
            // If we don't have a file path string here, the action was
            // cancelled and we should respond with the cancellation.
            if (!is_string($dbPath)) {
                return $dbPath;
            }
        }

        if (str_ends_with($dbPath, 'sql.gz')) {
            $importFile = substr($dbPath, 0, -3);
            $gzip = TRUE;
        }
        elseif (str_ends_with($dbPath, '.sql')) {
            $importFile = $dbPath;
            $gzip = FALSE;
        }
        else {
            throw new TaskException(
                $this,
                'Data import file must either be a .sql file or a .sql.gz file.',
            );
        }

        $io->section("refreshing $siteName database.");
        $io->say("Dropping existing database for $siteName");
        $this->taskExec('drush')
            ->arg('sql:drop')
            ->option('uri', $siteName)
            ->option('yes')
            ->run();
        // If a database was downloaded as part of this process, delete it.
        if ($dbPathProvidedByUser) {
            if ($gzip) {
                $this->_exec("gunzip --force --keep '$dbPath'");
            }
        }
        else {
            if ($gzip) {
                $this->_exec("gunzip --force '$dbPath'");
            }
        }
        $io->say('Importing data from: ' . $importFile);
        $this->_exec('$(drush sql:connect --uri="' . $siteName . '") < "' . $importFile .'"');
        if (!$dbPathProvidedByUser || ($dbPathProvidedByUser && $gzip)) {
            // gunzip without --keep deletes the sql.gz file while unzipping.
            // Delete the unzipped file.
            $this->deleteDataFile($importFile);
        }

        return $this->drushDeployWith(
            io: $io,
            localEnvironmentType: LocalDevEnvironmentTypes::DDEV,
            siteDir: $siteName,
        );
    }

    /**
     * Refresh database on Tugboat.
     */
    public function databaseRefreshTugboat(ConsoleIO $io): ResultData
    {
        $io->title('refresh tugboat databases.');
        $resultData = new ResultData();

        foreach (array_keys($this->getAllSitesConfig()) as $siteName) {
            $dbPath = '';
            try {
                $dbPath = $this->databaseDownload($io, $siteName);
            } catch (TaskException $e) {
                $io->yell("$siteName: No database configured. Download/import skipped.");
                $resultData->append($e->getMessage());
                // @todo: Should we run a site-install by default? The "common"
                // site ends up here, but we could change that.
                continue;
            }
            if (!is_string($dbPath) || $dbPath === '') {
                $io->yell("'$siteName' database path not found.");
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
            $io->section("import $siteName database.");
            $taskResult = $this->taskExec("zcat $dbPath | $dbDriver -h mariadb -u tugboat -ptugboat $dbName")
                ->run();
            $resultData->append($taskResult);
            $taskResult = $this->taskExec('rm')->args($dbPath)->run();
            $resultData->append($taskResult);
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
     *   Specify local development environment: ddev. This value is a string instead of LocalDevEnvironmentTypes since
     *   it is a public command that can be called from the command line.
     * @param string $siteDir
     *   The Drupal site directory name.
     */
    public function drupalLoginLink(
        ConsoleIO $io,
        string $environmentType,
        string $siteDir = 'default',
    ): Result {
        $io->section("create login link.");
        $uid = $this->getDrupalSiteAdminUid(siteName: $siteDir);
        if ($environmentType === 'ddev') {
            return $this->taskExec('drush')
                ->arg("@$siteDir.$environmentType")
                ->arg('user:login')
                ->option("--uid=$uid")
                ->dir("$this->drupalRoot/sites/$siteDir")
                ->run();
        }
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
     */
    public function frontendDevDisable(
        ConsoleIO $io,
        string $siteDir = 'default',
        array $opts = ['yes|y' => false]
    ): Result|ResultData {
        $devSettingsPath = "$this->drupalRoot/sites/$siteDir/settings.local.php";
        if (!$opts['yes']) {
            $io->yell("This command will overwrite any customizations you have made to $devSettingsPath and
                $this->devServicesPath.");
            $yes = $io->confirm('This command is destructive. Do you wish to continue?');
            if (!$yes) {
                return Result::cancelled();
            }
        }
        $io->title('disabling front-end development mode.');
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
        ConsoleIO $io,
        LocalDevEnvironmentTypes $environmentType,
        string $siteName = 'default',
        string $databasePath = '',
    ): Result|ResultData {
        $io->title('development environment refresh. 🦄✨');
        $this->taskComposerInstall()->run();
        // There isn't a great way to call a command in one class from another.
        // https://github.com/consolidation/Robo/issues/743
        // For now, it seems like calling robo from within robo works.
        $this->taskExec("composer robo theme:build $siteName")
            ->run();
        $this->frontendDevEnable($io, $siteName, ['yes' => true]);
        /** @var Result|ResultData $result */
        $result = $this->databaseRefreshDdev($io, siteName: $siteName, options: ['db' => $databasePath]);
        if ($result->wasCancelled() || $result->getExitCode() !== ResultData::EXITCODE_OK) {
            return $result;
        }

        return $this->drupalLoginLink($io, $environmentType->value, $siteName);
    }

    /**
     * Deploy with Drush via a local development environment.
     *
     * @see https://www.drush.org/deploycommand
     */
    protected function drushDeployWith(
        ConsoleIO $io,
        LocalDevEnvironmentTypes $localEnvironmentType,
        string $siteDir = 'default',
    ): Result {
        $io->section('drush deploy.');
        if (!class_exists(\Drush\Commands\core\DeployCommands::class)) {
            throw new TaskException(
                $this,
                "'drush deploy' command not found. Further work is necessary to support this version of Drush."
            );
        }
        // After drush deploy, re-import the latest configuration. This includes
        // the latest configuration_split configuration. Importing this twice
        // ensures that the latter command enables and disables modules based
        // upon the most up--to-date configuration. More at:
        // https://github.com/drush-ops/drush/issues/2449#issuecomment-708655673

        // Currently we only have one local environment type:
        // LocalDevEnvironmentTypes::DDEV, so no need for other implementations.
        return $this->taskExecStack()
            ->dir("$this->drupalRoot/sites/$siteDir")
            ->exec("drush @$siteDir.$localEnvironmentType->value deploy --yes")
            ->exec("drush @$siteDir.$localEnvironmentType->value config:import --yes")
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
     */
    public function frontendDevEnable(
        ConsoleIO $io,
        string $siteDir = 'default',
        array $opts = ['yes|y' => false],
    ): Result|ResultData {
        $devSettingsPath = "$this->drupalRoot/sites/$siteDir/settings.local.php";

        if (!$opts['yes']) {
            $io->yell("This command will overwrite any customizations you have made to $devSettingsPath and
                $this->devServicesPath.");
            $yes = $io->confirm('This command is destructive. Do you wish to continue?');
            if (!$yes) {
                return Result::cancelled();
            }
        }

        $io->title('enabling front-end development mode.');
        $io->say("copying settings.local.php and development.services.yml into sites/$siteDir.");

        // Copy the example local settings file.
        $example_local_settings_file = "$this->drupalRoot/sites/example.settings.local.php";
        if (file_exists($example_local_settings_file)) {
            $this->taskFilesystemStack()
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
            $this->taskFilesystemStack()
                ->copy($development_services_file, $this->devServicesPath, true)
                ->run();
        } else {
            throw new TaskException(
                $this,
                "The \"$development_services_file\" file was not found."
            );
        }

        $io->say("optimizing twig for front-end development in development services yml config.");
        $devServices = Yaml::parseFile($this->devServicesPath);
        $devServices['parameters']['twig.config'] = [
            'debug' => true,
            'auto_reload' => true,
            'cache' => false,
        ];
        $this->writeYaml($this->devServicesPath, $devServices);
        $io->say("disabling render and dynamic_page_cache in settings.local.php.");
        // https://github.com/consolidation/robo/issues/1059#issuecomment-967732068
        return $this->collectionBuilder()
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
    }
}
