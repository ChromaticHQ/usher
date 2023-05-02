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
     * A comma separated list of the file extensions PHPCS should check.
     *
     * @var string
     */
    protected $phpcsCheckExtensions;

    /**
     * A comma separated list of the paths PHPCS should ignore.
     *
     * @var string
     */
    protected $phpcsIgnorePaths;

    /**
     * A space separated list of custom code paths.
     *
     * @var string
     */
    protected $customCodePaths;

    /**
     * A comma separated list of PHPCS standards.
     *
     * @var string
     */
    protected $phpcsStandards;

    /**
     * The PHP version to lint against for support.
     *
     * @var string
     */
    protected $phpcsPhpVersion;

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

        $this->phpcsCheckExtensions = implode(',', Robo::config()->get('phpcs_check_extensions'));
        $this->phpcsIgnorePaths = implode(',', $this->getRoboConfigArrayFor('phpcs_ignore_paths'));
        $this->customCodePaths = implode(' ', $this->getRoboConfigArrayFor('custom_code_paths'));
        $this->phpcsStandards = implode(',', $this->getRoboConfigArrayFor('phpcs_standards'));
        $this->lintTwigFiles = Robo::config()->get('twig_lint_enable') ?? true;
        $this->phpcsPhpVersion = Robo::config()->get('phpcs_php_version', $this::PHPCS_DEFAULT_PHP_VERSION);
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
     * @aliases phpcs twigcs
     *
     * @return \Robo\Result
     *   The result of the set of tasks.
     */
    public function jobCheckCodingStandards(): Result
    {
        return $this->jobRunCodingStandards(false);
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
        return $this->jobRunCodingStandards(true);
    }

    /**
     * Determine whether to check or fix coding standard issues and run as requested.
     *
     * @param bool $applyFixes
     *   FALSE if phpcs should be run, else phpcbf.
     *
     * @return \Robo\Result
     *   The result of the set of tasks.
     */
    protected function jobRunCodingStandards(bool $applyFixes = false): Result
    {
        /** @var \Robo\Task\CommandStack $stack */
        $stack = $this->taskExecStack()->stopOnFail();
        $phpBinary = $applyFixes ? 'phpcbf' : 'phpcs';
        // General PHP linting.
        $stack->exec("vendor/bin/$phpBinary --standard=$this->phpcsStandards --extensions=$this->phpcsCheckExtensions \
                --ignore=$this->phpcsIgnorePaths $this->customCodePaths");
        // Check for PHP version compatibility.
        // The trailing dash after the version runs checks for the specified
        // version and above.
        // @see https://github.com/PHPCompatibility/PHPCompatibility#sniffing-your-code-for-compatibility-with-specific-php-versions
        $stack->exec("vendor/bin/phpcs --standard=PHPCompatibility --severity=1 \
                --ignore=$this->phpcsIgnorePaths --extensions=php,module,theme \
                --runtime-set testVersion $this->phpcsPhpVersion- $this->customCodePaths");
        // Lint Twig files.
        if ($this->lintTwigFiles) {
            $fixFlag = $applyFixes ? '--fix' : '';
            $stack->exec("vendor/bin/twig-cs-fixer lint $this->customCodePaths $fixFlag");
        }

        return $stack->run();
    }
}
