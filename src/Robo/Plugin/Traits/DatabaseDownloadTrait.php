<?php

namespace Usher\Robo\Plugin\Traits;

use AsyncAws\Core\Exception\Http\ClientException;
use AsyncAws\Core\Sts\StsClient;
use AsyncAws\S3\S3Client;
use AsyncAws\S3\ValueObject\AwsObject;
use Robo\Exception\AbortTasksException;
use Robo\Exception\TaskException;
use Robo\Result;
use Robo\ResultData;
use Robo\Symfony\ConsoleIO;

/**
 * Trait to provide database download functionality to Robo commands.
 */
trait DatabaseDownloadTrait
{
    /**
     * Default S3 region.
     */
    protected string $s3DefaultRegion = 'us-east-1';

    /**
     * Download the latest database dump for the site.
     *
     * @param string $siteName
     *   The site name.
     *
     * @aliases dbdl
     *
     * @throws \Robo\Exception\TaskException|\Robo\Exception\AbortTasksException
     */
    public function databaseDownload(ConsoleIO $io, string $siteName = 'default'): string|ResultData
    {
        $io->title('database download.');

        $authenticated = false;
        $objects = null;
        $region = $this->s3RegionForSite($siteName);
        $s3Bucket = $this->s3BucketForSite($siteName);
        $s3Prefix = $this->s3PrefixForSite($siteName);
        $requestConfig = ['Bucket' => $s3Bucket];
        if ($s3Prefix !== '') {
            $requestConfig[] = ['Prefix' => $s3Prefix];
        }
        $s3 = new S3Client(['region' => $region]);
        try {
            $io->say("Connecting to S3...");
            $objects = $s3->listObjectsV2($requestConfig);
            $objects->resolve();
            $authenticated = $objects->info()['status'] === 200;
        } catch (ClientException $e) {
            $io->error($e->getMessage());
        }
        if (!$authenticated) {
            if (getenv('AWS_SECRET_ACCESS_KEY')) {
                $io->error([
                    'Cannot authenticate to AWS S3. Please fix your AWS environment',
                    'variables in .env, or remove them completely and try again.',
                ]);
                return Result::cancelled();
            }
            $io->say("Unable to authenticate to AWS S3.
            You can either set your credentials as environment variables and try again,
            or you can continue by configuring your AWS credentials file.");
            $result = $this->configureAwsCredentials($io);
            if ($result->wasCancelled()) {
                return $result;
            }
            $s3 = new S3Client(['region' => $region]);
            try {
                $objects = $s3->listObjectsV2($requestConfig);
            } catch (\Exception $e) {
                $io->error($e->getMessage());
                throw new AbortTasksException('Unable to access AWS S3. Giving up.');
            }
        }
        $objects = iterator_to_array($objects);
        /** @var AwsObject[] $objects */
        if ($objects === []) {
            throw new TaskException($this, "No database dumps found for '$siteName'.");
        }
        // Ensure objects are sorted by last modified date.
        usort(
            array: $objects,
            callback: fn(AwsObject $a, AwsObject $b) =>
                $a->getLastModified()->getTimestamp() <=> $b->getLastModified()->getTimestamp(),
        );
        /** @var \AsyncAws\S3\ValueObject\AwsObject $latestDatabaseDump */
        $latestDatabaseDump = array_pop(array: $objects);
        $dbFilename = $latestDatabaseDump->getKey();
        $downloadFileName = $this->sanitizeFileNameForWindows($dbFilename);

        if (file_exists($downloadFileName)) {
            $this->say("Skipping download. Latest database dump file exists >>> $downloadFileName");
        } else {
            $result = $s3->getObject([
                'Bucket' => $s3Bucket,
                'Key' => $dbFilename,
            ]);
            stream_copy_to_stream(
                from: $result->getBody()->getContentAsResource(),
                to: fopen($downloadFileName, 'wb'),
            );
            $this->say("Database dump file downloaded >>> $downloadFileName");
        }
        return $downloadFileName;
    }

    /**
     * Configure AWS credentials.
     */
    protected function configureAwsCredentials(ConsoleIO $io): Result|ResultData
    {
        $awsConfigDirPath = getenv('HOME') . '/.aws';
        $awsConfigFilePath = "$awsConfigDirPath/credentials";
        $persistentCredentialsPath = '.ddev/homeadditions/.aws';
        $authenticated = false;

        while ($authenticated === false) {
            $yes = $io->confirm('Do you wish to configure your AWS S3 credentials file?');
            if (!$yes) {
                return Result::cancelled();
            }
            if (!is_dir($awsConfigDirPath)) {
                $this->_mkdir($awsConfigDirPath);
            }
            if (!file_exists($awsConfigFilePath)) {
                $this->_touch($awsConfigFilePath);
            }
            $awsKeyId = $io->ask("AWS Access Key ID:");
            $awsSecretKey = $io->askHidden("AWS Secret Access Key:");
            $collection = $this->collectionBuilder($io);
            $collection->taskWriteToFile($awsConfigFilePath)
                ->line('[default]')
                ->line("aws_access_key_id = $awsKeyId")
                ->line("aws_secret_access_key = $awsSecretKey");
            $collection->taskCopyDir([$awsConfigDirPath => $persistentCredentialsPath])
                ->overwrite(true);
            $writeResult = $collection->run();
            try {
                $sts = new StsClient();
                $stsResponse = $sts->getCallerIdentity();
                // Ensure the request is completed.
                if ($stsResponse->resolve()) {
                    $authenticated = $stsResponse->info()['status'] === 200;
                }
            } catch (\Exception $e) {
                $io->error($e->getMessage());
            }
        }

        return $writeResult;
    }

    /**
     * Get S3 Bucket for site.
     *
     * @param string $siteName
     *   The site name.
     *
     * @throws \Robo\Exception\TaskException
     */
    protected function s3BucketForSite(string $siteName): string
    {
        if (!is_string($bucket = $this->getSiteConfigItem('database_s3_bucket', $siteName, true))) {
            throw new TaskException($this, "database_s3_bucket value not set for '$siteName'.");
        }
        $this->say("'$siteName' S3 bucket: $bucket");
        return $bucket;
    }

    /**
     * Get S3 Prefix from sites config.
     *
     * @param string $siteName
     *   The site name.
     *
     * @throws \Robo\Exception\TaskException
     */
    protected function s3PrefixForSite(string $siteName): string
    {
        try {
            $s3KeyPrefix = $this->getSiteConfigItem('database_s3_key_prefix_string', $siteName);
        } catch (TaskException) {
            $this->say("No S3 Key prefix found for $siteName.");
        }
        if (isset($s3KeyPrefix) && is_string($s3KeyPrefix) && $s3KeyPrefix !== '') {
            $this->say("'$siteName' S3 Key prefix: '$s3KeyPrefix'");
            return $s3KeyPrefix;
        }
        return '';
    }

    /**
     * Get S3 region for site.
     *
     * @param string $siteName
     *   The site name.
     */
    protected function s3RegionForSite(string $siteName): string
    {
        try {
            $region = $this->getSiteConfigItem('database_s3_region', $siteName);
            $this->say("'$siteName' database_s3_region set to $region.");
        } catch (TaskException) {
            // Set default region if one is not set.
            $defaultRegion = $this->s3DefaultRegion;
            $this->say("'$siteName' database_s3_region not set. Defaulting to $defaultRegion.");
            $region = $defaultRegion;
        }
        return $region;
    }

    /**
     * Sanitizes a file name for the Windows file system.
     *
     * @param string $fileName
     *   The file name to sanitize.
     */
    public function sanitizeFileNameForWindows(string $fileName): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $fileName = preg_replace(
                '~
                # File system reserved characters.
                # @link https://en.wikipedia.org/wiki/Filename#Reserved_characters_and_words
                [<>:"/\\\|?*]|
                # Control characters.
                # @link https://docs.microsoft.com/en-gb/windows/win32/fileio/naming-a-file
                [\x00-\x1F]|
                # Non-printing characters DEL, NO-BREAK SPACE, SOFT HYPHEN.
                [\x7F\xA0\xAD]
                ~x',
                '_',
                $fileName
            );
        }
        return $fileName;
    }

    /**
     * Delete the specified database.
     */
    protected function deleteDatabase(string $dbPath): Result
    {
        $this->say("Deleting $dbPath");
        return $this->taskExec('rm')->args($dbPath)->run();
    }
}
