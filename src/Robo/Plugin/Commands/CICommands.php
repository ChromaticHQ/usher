<?php

namespace Usher\Robo\Plugin\Commands;

use Robo\Exception\TaskException;
use Robo\Result;
use Robo\Robo;
use Robo\Tasks;
use Usher\Robo\Plugin\Traits\RoboConfigTrait;

/**
 * Robo commands related to continuous integration.
 */
class CICommands extends Tasks
{
    use RoboConfigTrait;

    /**
     * The default PHP version to lint against.
     *
     * @var string
     */
    protected const PHPCS_DEFAULT_PHP_VERSION = '8.1';

    /**
     * RoboFile constructor.
     */
    public function __construct()
    {
        // Treat this command like bash -e and exit as soon as there's a failure.
        $this->stopOnFail();
    }

    /**
     * Command to run unit tests.
     *
     * @aliases punit
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
     * @throws \Robo\Exception\TaskException
     */
    public function jobRunStaticAnalysis(): Result
    {
        $customCodePaths = $this->getCustomCodePaths();
        return $this->taskExecStack()
            ->stopOnFail()
            ->exec("vendor/bin/phpstan analyse --memory-limit=1G $customCodePaths")
            ->run();
    }

    /**
     * Command to check coding standards.
     *
     * @aliases phpcs twigcs
     */
    public function jobCheckCodingStandards(): Result
    {
        return $this->jobRunCodingStandards(false);
    }

    /**
     * Command to fix coding standards where possible.
     *
     * @aliases phpfix
     */
    public function jobFixCodingStandards(): Result
    {
        return $this->jobRunCodingStandards(true);
    }

    /**
     * Determine whether to check or fix coding standard issues and run as requested.
     *
     * @param bool $applyFixes
     *   FALSE if phpcs should be run, else phpcbf.
     */
    protected function jobRunCodingStandards(bool $applyFixes = false): Result
    {
        $customCodePaths = $this->getCustomCodePaths();
        $phpcsStandards = implode(
            separator: ',',
            array: $this->getRequiredRoboConfigArrayFor(key: 'phpcs_standards'),
        );
        $phpcsCheckExtensions = implode(
            separator: ',',
            array: $this->getRequiredRoboConfigArrayFor(key: 'phpcs_check_extensions'),
        );
        $phpcsIgnorePaths = implode(
            separator: ',',
            array: $this->getRequiredRoboConfigArrayFor(key: 'phpcs_ignore_paths'),
        );
        $phpcsPhpVersion = Robo::config()->get('php_current_version', $this::PHPCS_DEFAULT_PHP_VERSION);
        $twigLintEnabled = Robo::config()->get('twig_lint_enable') ?? true;

        /** @var \Robo\Task\CommandStack $stack */
        $stack = $this->taskExecStack()->stopOnFail();
        $phpBinary = $applyFixes ? 'phpcbf' : 'phpcs';
        // General PHP linting.
        $stack->exec("vendor/bin/$phpBinary --standard=$phpcsStandards --extensions=$phpcsCheckExtensions \
                --ignore=$phpcsIgnorePaths $customCodePaths");
        // Check for PHP version compatibility.
        // The trailing dash after the version runs checks for the specified
        // version and above.
        // @see https://github.com/PHPCompatibility/PHPCompatibility#sniffing-your-code-for-compatibility-with-specific-php-versions
        $stack->exec("vendor/bin/phpcs --standard=PHPCompatibility --severity=1 \
                --ignore=$phpcsIgnorePaths --extensions=php,module,theme \
                --runtime-set testVersion $phpcsPhpVersion- $customCodePaths");
        // Lint Twig files.
        if ($twigLintEnabled == true) {
            $fixFlag = $applyFixes ? '--fix' : '';
            $stack->exec("vendor/bin/twig-cs-fixer lint $customCodePaths $fixFlag");
        }

        return $stack->run();
    }

    /**
     * Get the list of custom code paths to check.
     */
    protected function getCustomCodePaths(): string
    {
        return implode(
            separator: ' ',
            array: $this->getRequiredRoboConfigArrayFor(key: 'custom_code_paths'),
        );
    }
}
