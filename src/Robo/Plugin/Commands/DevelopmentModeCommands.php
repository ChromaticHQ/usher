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
class DevelopmentModeCommands extends Tasks
{
    use SitesConfigTrait;

    protected const S3_DEFAULT_REGION = 'us-east-1';

    /**
     * Drupal root directory.
     *
     * @var string
     */
    protected $drupalRoot;

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
        $this->devServicesPath = "$this->drupalRoot/sites/fe.development.services.yml";
    }

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
    public function devRefresh($siteName = 'default'): Result
    {
        $this->io()->title('development environment refresh. 🦄✨');
        $result = $this->taskComposerInstall()->run();
        $result = $this->taskExec('lando')->arg('start')->run();
        // There isn't a great way to call a command in one class from another.
        // https://github.com/consolidation/Robo/issues/743
        // For now, it seems like calling robo from within robo works.
        $result = $this->taskExec("composer robo theme:build $siteName")
            ->run();
        $result = $this->frontendDevEnable($siteName, ['yes' => true]);
        $result = $this->databaseRefreshLando($siteName);
        $result = $this->drupalLoginLink($siteName);
        return $result;
    }

    /**
     * Download the latest database dump for the site.
     *
     * @param string $siteName
     *   The site name.
     *
     * @aliases dbdl
     *
     * @return string
     *   The path of the last downloaded database.
     *
     * @throws \Robo\Exception\TaskException
     */
    public function databaseDownload($siteName = 'default')
    {
        $this->io()->title('database download.');

        $awsConfigDirPath = getenv('HOME') . '/.aws';
        $awsConfigFilePath = "$awsConfigDirPath/credentials";
        if (!is_dir($awsConfigDirPath) || !file_exists($awsConfigFilePath)) {
            $result = $this->configureAwsCredentials($awsConfigDirPath, $awsConfigFilePath);
            if ($result->wasCancelled()) {
                return Result::cancelled();
            }
        }

        $s3 = new S3Client([
            'region' => $this->s3RegionForSite($siteName),
        ]);
        $objects = $s3->listObjectsV2($this->s3BucketRequestConfig($siteName));
        $objects = iterator_to_array($objects);
        if (empty($objects)) {
             throw new TaskException($this, "No database dumps found for '$siteName'.");
        }
        // Ensure objects are sorted by last modified date.
        usort($objects, fn($a, $b) => $a->getLastModified()->getTimestamp() <=> $b->getLastModified()->getTimestamp());
        $latestDatabaseDump = array_pop($objects);
        $dbFilename = $latestDatabaseDump->getKey();

        if (file_exists($dbFilename)) {
            $this->say("Skipping download. Latest database dump file exists >>> $dbFilename");
        } else {
            $result = $s3->GetObject([
                'Bucket' => $this->s3BucketForSite($siteName),
                'Key' => $dbFilename,
            ]);
            $fp = fopen($dbFilename, 'wb');
            stream_copy_to_stream($result->getBody()->getContentAsResource(), $fp);
            $this->say("Database dump file downloaded >>> $dbFilename");
        }
        return $dbFilename;
    }

    /**
     * Configure AWS credentials.
     *
     * @param string $awsConfigDirPath
     *   Path to the AWS configuration directory.
     * @param string $awsConfigFilePath
     *   Path to the AWS configuration file.
     */
    protected function configureAwsCredentials(string $awsConfigDirPath, string $awsConfigFilePath)
    {
        $yes = $this->io()->confirm('AWS S3 credentials not detected. Do you wish to configure them?');
        if (!$yes) {
            return Result::cancelled();
        }

        if (!is_dir($awsConfigDirPath)) {
            $this->_mkdir($awsConfigDirPath);
        }

        if (!file_exists($awsConfigFilePath)) {
            $this->_touch($awsConfigFilePath);
        }

        $awsKeyId = $this->ask("AWS Access Key ID:");
        $awsSecretKey = $this->askHidden("AWS Secret Access Key:");
        return $this->taskWriteToFile($awsConfigFilePath)
            ->line('[default]')
            ->line("aws_access_key_id = $awsKeyId")
            ->line("aws_secret_access_key = $awsSecretKey")
            ->run();
    }

    /**
     * Build S3 request configuration from sites config.
     *
     * @param string $siteName
     *   The site name.
     *
     * @return array
     *   An S3 request object configuration array.
     */
    protected function s3BucketRequestConfig(string $siteName): array
    {
        $s3ConfigArray = ['Bucket' => $this->s3BucketForSite($siteName)];
        try {
            $s3KeyPrefix = $this->getConfig('database_s3_key_prefix_string', $siteName);
            $this->say("'$siteName' S3 Key prefix: '$s3KeyPrefix'");
            $s3ConfigArray['Prefix'] = $s3KeyPrefix;
        } catch (TaskException $e) {
            $this->say("No S3 Key prefix found for $siteName.");
        }
        return $s3ConfigArray;
    }

    /**
     * Get S3 Bucket for site.
     *
     * @param string $siteName
     *   The site name.
     *
     * @return string
     *   An S3 bucket.
     *
     * @throws \Robo\Exception\TaskException
     */
    protected function s3BucketForSite(string $siteName): string
    {
        if (!$bucket = $this->getConfig('database_s3_bucket', $siteName)) {
            throw new TaskException($this, "database_s3_bucket value not set for '$siteName'.");
        }
        $this->say("'$siteName' S3 bucket: $bucket");
        return $bucket;
    }

    /**
     * Get S3 region for site.
     *
     * @param string $siteName
     *   The site name.
     *
     * @return string
     *   An S3 region.
     */
    protected function s3RegionForSite(string $siteName): string
    {
        try {
            $region = $this->getConfig('database_s3_region', $siteName);
            $this->say("'$siteName' database_s3_region set to $region.");
        } catch (TaskException $e) {
            // Set default region if one is not set.
            $defaultRegion = self::S3_DEFAULT_REGION;
            $this->say("'$siteName' database_s3_region not set. Defaulting to $defaultRegion.");
            $region = $defaultRegion;
        }
        return $region;
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
     * Generate Drupal login link.
     *
     * @param string $siteDir
     *   The Drupal site directory name.
     * @param bool $lando
     *   Use lando to call drush, else call drush directly.
     *
     * @aliases uli
     *
     * @return \Robo\Result
     *   The result of the set of tasks.
     */
    public function drupalLoginLink($siteDir = 'default', $lando = true): Result
    {
        $this->io()->section("create login link.");
        $docRootDir = Robo::config()->get('drupal_document_root') ?? 'web';
        if ($lando) {
            $uri = $this->landoUri($siteDir);
            $this->say("Lando URI detected: $uri");
            return $this->taskExec('lando')
                ->arg('drush')
                ->arg('user:login')
                ->option('--uri', $uri)
                ->dir("$docRootDir/sites/$siteDir")
                ->run();
        }
        return $this->taskExec('../../../drush')
            ->arg('user:login')
            ->dir("$docRootDir/sites/$siteDir")
            ->run();
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
        $landoConfigPath = "$this->drupalRoot/../.lando.yml";
        $landoCfg = Yaml::parseFile($landoConfigPath);
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
        } elseif ($uri = $landoCfg['services']['appserver']['overrides']['environment']['DRUSH_OPTIONS_URI'] ?? null) {
            // If a Drush URI is explicitly set, use that.
            return $uri;
        } else {
            // Our final fallback.
            return 'http://' . $landoCfg['name'] . 'lndo.site';
        }
        throw new TaskException($this, 'Unable to determine URI.');
    }

    /**
     * Refresh database on Tugboat.
     *
     * @return null|\Robo\Result
     *   The task result.
     */
    public function databaseRefreshTugboat(): Result
    {
        $this->io()->title('refresh tugboat databases.');
        foreach ($this->getAllSitesConfig() as $siteName => $siteInfo) {
            try {
                $dbPath = $this->databaseDownload($siteName);
            } catch (TaskException $e) {
                $this->yell("$siteName: No database configured. Download/import skipped.");
                // @todo: Should we run a site-install by default?
                continue;
            }
            if (empty($dbPath)) {
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
     * Deploy Drush via Lando.
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
                "'drush deploy' command not found. Further work is neccesary to support this version of Drush."
            );
        }
        $docRootDir = Robo::config()->get('drupal_document_root') ?? 'web';
        return $this->taskExecStack()
            ->dir("$docRootDir/sites/$siteDir")
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
    public function frontendDevEnable($siteDir = 'default', array $opts = ['yes|y' => false])
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

        $result = $this->taskFilesystemStack()
            ->copy("$this->drupalRoot/sites/example.settings.local.php", $devSettingsPath, true)
            ->copy("$this->drupalRoot/sites/development.services.yml", $this->devServicesPath, true)
            ->run();

        $this->say("enablig twig.debug in development.services.yml.");
        $devServices = Yaml::parseFile($this->devServicesPath);
        $devServices['parameters']['twig.config'] = [
            'debug' => true,
            'auto_reload' => true,
        ];
        file_put_contents($this->devServicesPath, Yaml::dump($devServices));

        $this->say("disabling render and dynamic_page_cache in settings.local.php.");
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
        return $this->collectionBuilder()
            ->taskFilesystemStack()
            ->remove($devSettingsPath)
            ->remove($this->devServicesPath)
            ->run();
    }

    /**
     * Setup a site in GitHub Codespaces.
     *
     * @throws \Robo\Exception\TaskException
     */
    public function setupCodespaces()
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

        $this->io()->section('Clearing Drupal cache.');
        $result = $this->taskExec('vendor/bin/drush cr')->run();

        $this->io()->section('Starting front-end development.');
        $result = $this->taskExec('yarn --cwd web/themes/chromatic/ start')->run();
        return $result;
    }
}
