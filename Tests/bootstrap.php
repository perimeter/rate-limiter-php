<?php

use Perimeter\RateLimiter\Tests\EntityManagerLoader;

/*
 * This file is part of the Perimeter package.
 *
 * (c) Adobe Systems, Inc. <bshafs@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

if (!file_exists($autoload_file = __DIR__.'/../vendor/autoload.php')) {
    if (empty($_SERVER['PERIMETER_VENDOR_AUTOLOAD'])) {
        throw new Exception('You must run composer.phar install, or set the PERIMETER_VENDOR_AUTOLOAD environment variable in your phpunit.xml to run the bundle tests');
    }

    $autoload_file = $_SERVER['PERIMETER_VENDOR_AUTOLOAD'];
}

require_once $autoload_file;

if (!EntityManagerLoader::updateDoctrineDB()) {
    throw new Exception('Cannot update Doctrine DB: ' . EntityManagerLoader::$errorMessage);
}