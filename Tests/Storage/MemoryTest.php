<?php

/*
 * This file is part of the Perimeter package.
 *
 * (c) Adobe Systems, Inc. <bshafs@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Perimeter\RateLimiter\Tests\Storage;

use Perimeter\RateLimiter\Storage\Memory;

class MemoryTest extends \PHPUnit_Framework_TestCase
{
    public function testGetMeter()
    {
        $meters = array(
            Memory::DEFAULT_METER_ID => array(
                'warn_threshold'  => 10,
                'limit_threshold' => 15,
            ),
            'adobe' => array(
                'warn_threshold'  => 20,
                'limit_threshold' => 30,
            ),
        );

        $memory = new Memory($meters);

        // test default meter
        $default = $memory->getMeter('something');
        $this->assertEquals(Memory::DEFAULT_METER_ID, $default['meter_id']);
        $this->assertEquals(10, $default['warn_threshold']);
        $this->assertEquals(15, $default['limit_threshold']);

        // test specific meter
        $adobe = $memory->getMeter('adobe');
        $this->assertEquals('adobe', $adobe['meter_id']);
        $this->assertEquals(20, $adobe['warn_threshold']);
        $this->assertEquals(30, $adobe['limit_threshold']);
    }

    /**
     * @expectedException Exception ::DEFAULT::
     */
    public function testGetMeterThrowsException()
    {
        $memory = new Memory(array());

        $memory->getMeter('test');
    }
}
