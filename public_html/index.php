<?php

/**
 * Main entry point
 */

require __DIR__ . '/../vendor/autoload.php';

$output = new \MediaWiki\Tools\ForceRebase\RequestHandler();
$output->run();
