<?php
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'bootstrap.php';

use nntmux\SphinxSearch;

if (!isset($argv[1]) || !in_array($argv[1], ['releases_rt', 'release_files_rt'])) {
	exit('Argument1 is the index name, currently only releases_rt/release_files_rt are supported.' . PHP_EOL);
}
(new SphinxSearch())->optimizeRTIndex($argv[1]);
