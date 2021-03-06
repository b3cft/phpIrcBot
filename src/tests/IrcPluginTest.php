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

use b3cft\IrcBot\IrcBot;

/* Include PSR0 Autoloader and add dev path to search */
if (false === defined('PSR0AUTOLOADER'))
{
    include_once 'gwc.autoloader.php';
    $devPath = realpath(dirname(__FILE__).'/../');
    if (false === empty($devPath))
    {
        __gwc_autoload_alsoSearch($devPath);
    }
    define('PSR0AUTOLOADER', true);
}
if (false === defined('PRS0PLUGINS'))
{
    define('PHP_DIR', '@@PHP_DIR@@');
    if (PHP_DIR !== '@@'."PHP_DIR".'@@')
    {
        __gwc_autoload_alsoSearch(PHP_DIR.'/b3cft/IRCBot/plugins');
    }
    $devPath = realpath(dirname(__FILE__).'/../b3cft/IrcBot/plugins/');
    if (false === empty($devPath))
    {
        __gwc_autoload_alsoSearch($devPath);
    }
    define('PRS0PLUGINS', true);
}

/**
 * Test ircPlugin methods
 *
 * @author b3cft
 */
class IrcPluginTest extends PHPUnit_Framework_TestCase
{

    private $client;
    private $config;

    /**
     * Create mock ircConnection and configs for each test
     *
     * @return void
     */
    public function setUp()
    {
        $this->config = array(
            'enabled' => 'true',
            'users'   => 'one,two,three',
        );
        $this->client = $this->getMock(
            'b3cft\IrcBot\ircConnection',
            array('writeline', '__destruct'), array(), '', false
        );
    }

    /**
     * Test constructor
     *
     * @return void
     */
    public function testConstruct()
    {
        $plugin = new ops($this->client, $this->config);

        $this->assertAttributeEquals(array('one'=>0,'two'=>1,'three'=>2), 'authUsers', $plugin);
    }

    /**
     * Test user authorisation
     *
     * @return void
     */
    public function testIsAuthorised()
    {
        $plugin = new ops($this->client, $this->config);

        $this->client->expects($this->never())
            ->method('writeline');

        $method = new ReflectionMethod('b3cft\IrcBot\ircPlugin', 'isAuthorised');
        $method->setAccessible(true);
        $result = $method->invoke($plugin, 'two');

        $this->assertTrue($result);
    }

    /**
     * Test user authorisation
     *
     * @return void
     */
    public function testIsAuthorisedOnlyWildcard()
    {
        $this->config['users'] = '*';
        $plugin = new ops($this->client, $this->config);

        $this->client->expects($this->never())
            ->method('writeline');

        $method = new ReflectionMethod('b3cft\IrcBot\ircPlugin', 'isAuthorised');
        $method->setAccessible(true);
        $result = $method->invoke($plugin, 'two');

        $this->assertTrue($result);
    }

    /**
     * Test user authorisation
     *
     * @return void
     */
    public function testIsAuthorisedAddedWildcard()
    {
        $this->config['users'] .= ',*';
        $plugin = new ops($this->client, $this->config);

        $this->client->expects($this->never())
            ->method('writeline');

        $method = new ReflectionMethod('b3cft\IrcBot\ircPlugin', 'isAuthorised');
        $method->setAccessible(true);
        $result = $method->invoke($plugin, 'four');

        $this->assertTrue($result);
    }


    /**
     * Test user authorisation
     *
     * @return void
     */
    public function testIsNotAuthorised()
    {
        $plugin = new ops($this->client, $this->config);

        $this->client->expects($this->once())
            ->method('writeline')
            ->with('PRIVMSG four :Sorry, you\'re not in my authorised users list.');

        $method = new ReflectionMethod('b3cft\IrcBot\ircPlugin', 'isAuthorised');
        $method->setAccessible(true);
        $result = $method->invoke($plugin, 'four');

        $this->assertFalse($result);
    }

    /**
     * Test private property getter
     *
     * @return void
     */
    public function testGet()
    {
        $plugin = new ops($this->client, $this->config);

        $results = $plugin->authUsers;

        $this->assertEquals(array('one'=>0,'two'=>1,'three'=>2), $results);
    }

    /**
     * Test private property getter
     *
     * @return void
     */
    public function testGetNonExistantProperty()
    {
        $plugin = new ops($this->client, $this->config);

        $results = $plugin->WibbleWobbleWoo;

        $this->assertNull($results);
    }
}