<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;
use Rector\Php80\Rector\FunctionLike\MixedTypeRector;
use Rector\Php71\Rector\FuncCall\CountOnNullRector;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ]);

    // Define sets of rules.
    $rectorConfig->sets([
        LevelSetList::UP_TO_PHP_81,
        SetList::CODE_QUALITY
    ]);

    $rectorConfig->skip([
        MixedTypeRector::class,
        CountOnNullRector::class => [
            // @see https://github.com/rectorphp/rector/issues/8016
            __DIR__ . '/src/Robo/Plugin/Traits/SitesConfigTrait.php',
        ],
    ]);
};
