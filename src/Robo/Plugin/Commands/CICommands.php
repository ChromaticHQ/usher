<?php

namespace ChqRobo\Robo\Plugin\Commands;

use Robo\Exception\TaskException;
use Robo\Result;
use Robo\Robo;
use Robo\Tasks;

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

    /**
     * A space separated list of custom code paths.
     *
     * @var string
     */
    protected $customCodePaths;

    /**
     * Boolean indicating whether Twig files should be linted.
     *
     * @var bool
     */
    protected $lintTwigFiles;

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
        $this->lintTwigFiles = Robo::config()->get('twig_lint_enable', true);
    }

    /**
     * Command to run unit tests.
     *
     * @aliases punit
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
     * @aliases stan
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
     * @aliases phpcs
     *
     * @return \Robo\Result
     *   The result of the set of tasks.
     */
    public function jobCheckCodingStandards(): Result
    {
        $standards = implode(',', $this->getCodingStandards());
        $extensions = implode(',', $this->phpcsCheckExtensions);
        $ignorePaths = implode(',', $this->phpcsIgnorePaths);
        /** @var \Robo\Task\CommandStack $stack */
        $stack = $this->taskExecStack()->stopOnFail();
        $stack->exec('vendor/bin/phpcs --config-set installed_paths vendor/drupal/coder/coder_sniffer');
        $stack->exec("vendor/bin/phpcs --standard=$standards --extensions=$extensions \
            --ignore=$ignorePaths $this->customCodePaths");
        if ($this->lintTwigFiles) {
            $stack->exec("vendor/bin/twig-cs-fixer lint $this->customCodePaths");
        }
        return $stack->run();
    }

    /**
     * Command to fix coding standards where possible.
     *
     * @aliases phpfix
     *
     * @return \Robo\Result
     *   The result of the set of tasks.
     */
    public function jobFixCodingStandards(): Result
    {
        $standards = implode(',', $this->getCodingStandards());
        $extensions = implode(',', $this->phpcsCheckExtensions);
        $ignorePaths = implode(',', $this->phpcsIgnorePaths);
        /** @var \Robo\Task\CommandStack $stack */
        $stack = $this->taskExecStack()->stopOnFail();
        $stack->exec('vendor/bin/phpcbf --config-set installed_paths vendor/drupal/coder/coder_sniffer');
        $stack->exec("vendor/bin/phpcbf --standard=$standards --extensions=$extensions \
                --ignore=$ignorePaths $this->customCodePaths");
        if ($this->lintTwigFiles) {
            $stack->exec("vendor/bin/twig-cs-fixer lint $this->customCodePaths --fix");
        }

        return $stack->run();
    }

    /**
     * Get custom code paths for static analysis tools to use.
     *
     * @return array
     *   An array containing application custom code paths.
     *
     * @throws \Robo\Exception\TaskException
     */
    protected function getCustomCodePaths(): array
    {
        if (!$customCodePaths = Robo::config()->get('custom_code_paths')) {
            throw new TaskException($this, 'Expected Robo configuration not present: custom_code_paths');
        }
        return $customCodePaths;
    }

    /**
     * Get coding standard(s) to use in PHPCS checks.
     *
     * @return array
     *   An array containing application custom code paths.
     *
     * @throws \Robo\Exception\TaskException
     */
    protected function getCodingStandards(): array
    {
        if (!$phpcsStandards = Robo::config()->get('phpcs_standards')) {
            throw new TaskException($this, 'Expected Robo configuration not present: phpcs_standards');
        }
        return $phpcsStandards;
    }
}
