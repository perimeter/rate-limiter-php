Perimeter RateLimiter
=====================

[![Build Status](https://travis-ci.org/perimeter/rate-limiter-php.svg?branch=develop)](https://travis-ci.org/perimeter/rate-limiter-php)

Rate Limit those APIs!

Installation
------------

```
$ composer.phar require perimeter/rate-limiter-php:dev-develop
```

> This library can be used alongside the [perimeter/RateLimitBundle](https://github.com/perimeter/RateLimitBundle) for Symfony2 applications. See the repository for more instructions.

Get Started
-----------

Create your throttler:

```php
include_once('vendor/autoload.php');

$redis = new Predis\Client();
$throttler = new Perimeter\RateLimiter\Throttler\RedisThrottler($redis);
```

Ensure redis is running by executing the `redis-server` command on the web server where
the library is running:

```
$ redis-server
```

Now you can throttle your users accordingly!

```php
// Create a meter ID based on something unique to your user
// In this case we use the IP. This can also be a username,
// company, or some other authenticated property
$meterId = sprintf('ip_address:%s', $_SERVER['REMOTE_ADDR']);
$warnThreshold = 10;
$limitThreshold = 20;

// run the "consume" command
$throttler->consume($meterId, $warnThreshold, $limitThreshold);

if ($throttler->isLimitWarning()) {
    echo "slow down!";
}

if ($throttler->isLimitExceeded()) {
    exit("you have been rate limited");
}
```

And that's it!
