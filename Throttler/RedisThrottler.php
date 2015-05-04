<?php

/*
 * This file is part of the Perimeter package.
 *
 * (c) Adobe Systems, Inc. <bshafs@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Perimeter\RateLimiter\Throttler;

use Predis\ClientInterface;

class RedisThrottler implements ThrottlerInterface, ThrottlerAdminInterface
{
    protected $redis;
    protected $config;
    protected $debug;
    protected $limitWarning;
    protected $limitExceeded;

    public function __construct(ClientInterface $redis, $config = array(), $debug = false)
    {
        $this->config = array_merge(array(
            'server_count' => 1,
            'num_buckets'  => 5,
            'bucket_size'  => 60,
            'rate_period'  => 3600,
            'track_meters' => true,
        ), $config);

        $this->redis = $redis;
        $this->debug = $debug;
    }

    public function consume($meterId, $warnThreshold, $limitThreshold, $numTokens = 1, $throttleMilliseconds = 0, $time = null)
    {
        $this->limitWarning = false;
        $this->limitExceeded = false;

        $buckets = $this->getBuckets($time);

        // build list of redis keys for each bucket
        foreach ($buckets as $bucketStart) {
            $keys[] = sprintf('meter:%s:%d', $meterId, $bucketStart);
        }

        try {
            // incr current bucket
            $this->redis->incrby($keys[0], $numTokens);

            // multi-get all buckets
            $rates = call_user_func_array(array($this->redis, 'mget'), $keys);

            // check if this bucket is new, and if so set expires time
            if ($rates[0] === $numTokens) {
                // expire current bucket at the appropriate time (plus a hashed offset to stagger expirations)
                $expireAt = $buckets[0] + ($this->config['bucket_size'] * $this->config['num_buckets']);
                $this->redis->expireat($keys[0], $expireAt);
            }

            // extrapolate rate and account for total number of servers
            $actual = (array_sum($rates) / $this->config['num_buckets']) * ($this->config['rate_period'] / $this->config['bucket_size']) * $this->config['server_count'];

            if ($this->config['track_meters']) {
                $this->trackMeter($meterId, $buckets, $rates);
            }

            // check rate against configured limits
            if ($actual > $limitThreshold) {
                //Record that we rate limited
                $key = str_replace('meter', 'error', $keys[0]);
                $this->redis->incrby($key, $numTokens);
                $this->redis->expireat($key, $expireAt);
                $this->limitExceeded = true;
            } elseif ($actual > $warnThreshold) {
                //Record that we warned
                $key = str_replace('meter', 'warn', $keys[0]);
                $this->redis->incrby($key, $numTokens);
                $this->redis->expireat($key, $expireAt);
                $this->limitWarning = true;
            }
        } catch (\Exception $e) {
            if ($this->debug) {
                throw $e;
            }
        }

        // induce a delay on calls to this meter
        if ($throttleMilliseconds > 0) {
            usleep($throttleMilliseconds * 1000);
        }
    }

    public function isLimitWarning()
    {
        return $this->limitWarning;
    }

    public function isLimitExceeded()
    {
        return $this->limitExceeded;
    }

    public function getTopMeters($time = null)
    {
        $buckets = $this->getBuckets($time);
        $totals = array();

        foreach ($buckets as $i => $bucket) {
            $trackKey = sprintf('track:%s', $bucket);
            $meters = $this->redis->hgetall($trackKey);

            foreach ($meters as $meterId => $tokens) {
                if (!isset($totals[$meterId])) {
                    $totals[$meterId] = 0;
                }

                $totals[$meterId] += $tokens;
            }
        }

        // do we want to do this in PHP?
        asort($totals, SORT_NUMERIC);

        return array_reverse($totals);
    }

    /**
     * NOTE: If you are calling getTopMeters, it makes more
     * sense in this case to just sum these up rather than
     * call getTokenRate and run the function again
     */
    public function getTokenRate($time = null)
    {
        $meters = $this->getTopMeters($time);

        return array_sum($meters);
    }

    /**
     * This method allows you to change how the meter is tracked
     * So if you'd like to use a sorted set rather than a hash,
     * go on ahead!
     */
    protected function trackMeter($meterId, $buckets, $rates)
    {
        // create a key for this bucket's start time
        $trackKey = sprintf('track:%s', $buckets[0]);

        // track the meter key to this bucket with the number of times it was called
        $this->redis->hset($trackKey, $meterId, $rates[0]);

        // ensure this meter expires
        $expireAt = $buckets[0] + ($this->config['bucket_size'] * $this->config['num_buckets']);
        $this->redis->expireat($trackKey, $expireAt);
    }

    private function getBuckets($time = null)
    {
        $buckets = array();

        if (is_null($time)) {
            $time = time();
        }

        // create $config['num_buckets'] of $config['bucket_size'] seconds
        $buckets[0] = $time - ($time % $this->config['bucket_size']); // align to $config['bucket_size'] second boundaries

        for ($i=1; $i < $this->config['num_buckets']; $i++) {
            $buckets[$i] = $buckets[$i-1] - $this->config['bucket_size'];
        }

        return $buckets;
    }
}
