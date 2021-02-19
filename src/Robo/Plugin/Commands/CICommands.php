<?php

namespace CHQRobo\Robo\Plugin\Commands;

use Robo\Tasks;
use Robo\Result;
use Robo\Robo;

/**
 * Robo commands related to continuous integration.
 */
class CICommands extends Tasks
{

    /**
     * Array containing the file extensions PHPCS should check.
     *
     * @var array
     */
    protected $phpcsCheckExtensions;

    /**
     * Array containing paths PHPCS should ignore.
     *
     * @var array
     */
    protected $phpcsIgnorePaths;

    protected $customCodePaths;

    /**
     * RoboFile constructor.
     */
    public function __construct()
    {
      // Treat this command like bash -e and exit as soon as there's a failure.
        $this->stopOnFail();

        $this->phpcsCheckExtensions = Robo::config()->get('phpcs_check_extensions');
        $this->phpcsIgnorePaths = Robo::config()->get('phpcs_ignore_paths');
        $this->customCodePaths = implode(' ', $this->getCustomCodePaths());
    }

    /**
     * Command to run unit tests.
     *
     * @return \Robo\Result
     *   The result of the collection of tasks.
     */
    public function jobRunUnitTests(): Result
    {
        return $this->taskExec('XDEBUG_MODE=coverage vendor/bin/phpunit --debug --verbose')->run();
    }

    /**
     * Run phpstan static analysis command.
     *
     * @return \Robo\Result
     *   The result of the collection of tasks.
     *
     * @throws \Robo\Exception\TaskException
     */
    public function jobRunStaticAnalysis(): Result
    {
        return $this->taskExecStack()
        ->stopOnFail()
        ->exec("vendor/bin/phpstan analyse --memory-limit=1G $this->customCodePaths")
        ->run();
    }

    /**
     * Command to check coding standards.
     *
     * @return \Robo\Result
     *   The result of the set of tasks.
     *
     * @throws \Robo\Exception\TaskException
     */
    public function jobCheckCodingStandards(): Result
    {
        $standards = implode(',', $this->getCodingStandards());
        $extensions = implode(',', $this->phpcsCheckExtensions);
        $ignorePaths = implode(',', $this->phpcsIgnorePaths);
        return $this->taskExecStack()
        ->stopOnFail()
        ->exec('vendor/bin/phpcs --config-set installed_paths vendor/drupal/coder/coder_sniffer')
        ->exec("vendor/bin/phpcs --standard=$standards --extensions=$extensions --ignore=$ignorePaths $this->customCodePaths")
        ->run();
    }

    /**
     * Command to fix coding standards where possible.
     *
     * @return \Robo\Result
     *   The result of the set of tasks.
     *
     * @throws \Robo\Exception\TaskException
     */
    public function jobFixCodingStandards(): Result
    {
        $standards = implode(',', $this->getCodingStandards());
        $extensions = implode(',', $this->phpcsCheckExtensions);
        $ignorePaths = implode(',', $this->phpcsIgnorePaths);
        return $this->taskExecStack()
        ->stopOnFail()
        ->exec('vendor/bin/phpcbf --config-set installed_paths vendor/drupal/coder/coder_sniffer')
        ->exec("vendor/bin/phpcbf --standard=$standards --extensions=$extensions --ignore=$ignorePaths $this->customCodePaths")
        ->run();
    }

    /**
     * Get custom code paths for static analysis tools to use.
     *
     * @return array
     *   An array containing application custom code paths.
     */
    protected function getCustomCodePaths(): array
    {
        return Robo::config()->get('custom_code_paths');
    }

    /**
     * Get coding standard(s) to use in PHPCS checks.
     *
     * @return array
     *   An array containing application custom code paths.
     */
    protected function getCodingStandards(): array
    {
      // return ['PSR12'];
        return Robo::config()->get('phpcs_standards');
    }
}
