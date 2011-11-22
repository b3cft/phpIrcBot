<?php
/**
 * Copyright (c) 2011 b3cft
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 *   * Redistributions of source code must retain the above copyright
 *     notice, this list of conditions and the following disclaimer.
 *
 *   * Redistributions in binary form must reproduce the above copyright
 *     notice, this list of conditions and the following disclaimer in
 *     the documentation and/or other materials provided with the
 *     distribution.
 *
 *   * Neither the name of Gradwell dot com Ltd nor the names of his
 *     contributors may be used to endorse or promote products derived
 *     from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
 * FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
 * ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @package    b3cft
 * @subpackage IrcBot
 * @author     Andy 'Bob' Brockhurst, <andy.brockhurst@b3cft.com>
 * @license    http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @link       http://b3cft.github.com/phpIrcBot
 * @version    @@PACKAGE_VERSION@@
 */

use b3cft\IrcBot\IrcMessage;

/* Include PSR0 Autoloader and add dev path to search */
if (false === defined('psr0autoloader'))
{
    require_once 'gwc.autoloader.php';
    $devPath = realpath(dirname(__FILE__).'/../');
    if (false === empty($devPath))
    {
        __gwc_autoload_alsoSearch($devPath);
    }
    define('psr0autoloader', true);
}

/**
 *
 *
 * @author b3cft
 * @covers b3cft\IrcBot\ircMessage
 */
class ircMessageTest extends PHPUnit_Framework_TestCase
{

    /**
     * Test a general message is interpreted correctly.
     *
     * @return void
     */
    public function testBasicMessage()
    {
        $message = new ircMessage(':b3cft!b3cft@30D15FCA.3F0ED70D.833D86B.IP PRIVMSG #frameworks :welcome to the world of tomorrow', 'unittest');

        $this->assertAttributeEquals(true, 'isInChannel', $message);
        $this->assertAttributeEquals(false, 'isToMe', $message);
        $this->assertAttributeEquals('b3cft', 'from', $message);
        $this->assertAttributeEquals('#frameworks', 'to', $message);
        $this->assertAttributeEquals('#frameworks', 'channel', $message);
        $this->assertAttributeEquals('welcome to the world of tomorrow', 'message', $message);
        $this->assertAttributeEquals('PRIVMSG', 'action', $message);
    }

    /**
     * Test an in channel message directed to the bot is identified
     *
     * @return void
     */
    public function testChannelMessageToMe1()
    {
        $message = new ircMessage(':b3cft!b3cft@30D15FCA.3F0ED70D.833D86B.IP PRIVMSG #frameworks :unittest welcome to the world of tomorrow', 'unittest');

        $this->assertAttributeEquals(true, 'isInChannel', $message);
        $this->assertAttributeEquals(true, 'isToMe', $message);
        $this->assertAttributeEquals('b3cft', 'from', $message);
        $this->assertAttributeEquals('#frameworks', 'to', $message);
        $this->assertAttributeEquals('#frameworks', 'channel', $message);
        $this->assertAttributeEquals('welcome to the world of tomorrow', 'message', $message);
        $this->assertAttributeEquals('PRIVMSG', 'action', $message);
    }

    /**
     * Test an in channel message directed to the bot is identified
     *
     * @return void
     */
    public function testChannelMessageToMe2()
    {
        $message = new ircMessage(':b3cft!b3cft@30D15FCA.3F0ED70D.833D86B.IP PRIVMSG #frameworks :unittest: welcome to the world of tomorrow', 'unittest');

        $this->assertAttributeEquals(true, 'isInChannel', $message);
        $this->assertAttributeEquals(true, 'isToMe', $message);
        $this->assertAttributeEquals('b3cft', 'from', $message);
        $this->assertAttributeEquals('#frameworks', 'to', $message);
        $this->assertAttributeEquals('#frameworks', 'channel', $message);
        $this->assertAttributeEquals('welcome to the world of tomorrow', 'message', $message);
        $this->assertAttributeEquals('PRIVMSG', 'action', $message);
    }

    /**
     * Test a direct message directed to the bot is identified
     *
     * @return void
     */
    public function testDirectMessageToMe()
    {
        $message = new ircMessage(':b3cft!b3cft@30D15FCA.3F0ED70D.833D86B.IP PRIVMSG unittest :welcome to the world of tomorrow', 'unittest');

        $this->assertAttributeEquals(false, 'isInChannel', $message);
        $this->assertAttributeEquals(true, 'isToMe', $message);
        $this->assertAttributeEquals('b3cft', 'from', $message);
        $this->assertAttributeEquals('unittest', 'to', $message);
        $this->assertAttributeEquals('b3cft', 'channel', $message);
        $this->assertAttributeEquals('welcome to the world of tomorrow', 'message', $message);
        $this->assertAttributeEquals('PRIVMSG', 'action', $message);
    }

    public function testModeMessage()
    {
        $message = new ircMessage(':overlord!andy.brock@30D15FCA.3F0ED70D.833D86B.IP MODE #frameworks +o b3cft', 'unittest');

        $this->assertAttributeEquals(true, 'isInChannel', $message);
        $this->assertAttributeEquals(false, 'isToMe', $message);
        $this->assertAttributeEquals('overlord', 'from', $message);
        $this->assertAttributeEquals('b3cft', 'to', $message);
        $this->assertAttributeEquals('#frameworks', 'channel', $message);
        $this->assertAttributeEquals(null, 'message', $message);
        $this->assertAttributeEquals('MODE', 'action', $message);
    }

    public function testJoinMessage()
    {
        $message = new ircMessage(':b3cft!b3cft@30D15FCA.3F0ED70D.833D86B.IP JOIN :#frameworks', 'unittest');

        $this->assertAttributeEquals(true, 'isInChannel', $message);
        $this->assertAttributeEquals(false, 'isToMe', $message);
        $this->assertAttributeEquals('b3cft', 'from', $message);
        $this->assertAttributeEquals(null, 'to', $message);
        $this->assertAttributeEquals('#frameworks', 'channel', $message);
        $this->assertAttributeEquals(null, 'message', $message);
        $this->assertAttributeEquals('JOIN', 'action', $message);
    }

    public function testGet()
    {
        $message = new ircMessage(':b3cft!b3cft@30D15FCA.3F0ED70D.833D86B.IP PRIVMSG unittest :welcome to the world of tomorrow', 'unittest');
        $this->assertAttributeEquals('welcome to the world of tomorrow', 'message', $message);
        $value = $message->message;
        $this->assertEquals('welcome to the world of tomorrow', $value);
    }

    public function testGetNonExistant()
    {
        $message  = new ircMessage(':b3cft!b3cft@30D15FCA.3F0ED70D.833D86B.IP PRIVMSG unittest :welcome to the world of tomorrow', 'unittest');
        $property = __METHOD__;
        $value    = $message->$property;
        $this->assertNull($value);
    }
}