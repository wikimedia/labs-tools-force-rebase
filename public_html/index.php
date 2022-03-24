<?php

/**
 * Main entry point
 */

require __DIR__ . '/../vendor/autoload.php';

$output = new \MediaWiki\Tools\ForceRebase\WebOutput();
$output->run();
