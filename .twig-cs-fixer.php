<?php

$finder = new TwigCsFixer\File\Finder();
$finder->exclude('node_modules');

$config = new TwigCsFixer\Config\Config();
$config->setFinder($finder);

return $config;
