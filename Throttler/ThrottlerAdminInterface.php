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

interface ThrottlerAdminInterface
{
    public function getTopMeters($time = null);
    public function getTokenRate($time = null);
}
