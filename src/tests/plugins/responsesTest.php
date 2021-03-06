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

use b3cft\IrcBot\ircMessage;

/* Include PSR0 Autoloader and add dev path to search */
if (false === defined('PSR0AUTOLOADER'))
{
    include_once 'gwc.autoloader.php';
    $devPath = realpath(dirname(__FILE__).'/../../');
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
    $devPath = realpath(dirname(__FILE__).'/../../b3cft/IrcBot/plugins/');
    if (false === empty($devPath))
    {
        __gwc_autoload_alsoSearch($devPath);
    }
    define('PRS0PLUGINS', true);
}

/**
 *
 *
 * @author b3cft
 */
class responsesTest extends PHPUnit_Framework_TestCase
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
            'enabled'  => 'true'
        );
        $this->client = $this->getMock(
            'b3cft\IrcBot\ircConnection',
            array('writeline', 'getMessageStack', '__destruct'), array(), '', false
        );
    }

    /**
     * Test get commands method returns correct list of commands we support
     *
     * @return void
     */
    public function testGetCommands()
    {
        $plugin = new responses($this->client, $this->config);

        $keys = $plugin->getCommands();
        $this->assertEmpty($keys);
    }

    /**
     * Test responses responses
     *
     * @param string $message   - message to respond to
     * @param mixed  $responses - expected repsonses
     *
     * @dataProvider responsesDataProvider
     * @group        dataprovider
     *
     * @return void
     */
    public function testResponses($message, $responses)
    {
        $this->config['paranoia'] = 0;

        $plugin = new responses($this->client, $this->config);

        if (true === is_null($responses))
        {
            $this->client->expects($this->never())
                ->method('writeline');
        }
        else if (is_string($responses))
        {
            if ('/' === $responses[0])
            {
                $match = $this->matchesRegularExpression($responses);
            }
            else
            {
                $match = $responses;
            }
            $this->client->expects($this->once())
                ->method('writeline')
                ->with($match);
        }
        else if (true === is_array($responses))
        {
            foreach ($responses as $index => $response)
            {
                $this->client->expects($this->at($index))
                    ->method('writeline')
                    ->with($response);
            }
        }

        $plugin->process(new ircMessage($message, 'unit'));
    }

    /**
     * Data provider for testResponses
     *
     * @return mixed[]
     */
    public function responsesDataProvider()
    {
        return array(
           array(
                ':one!one@1.2.3 PRIVMSG #test :surely this should work.',
                'PRIVMSG #test :one: Please don\'t call me Shirley.',
            ),
            array(
                ':one!one@1.2.3 PRIVMSG #test :that should work surely?',
                'PRIVMSG #test :one: Please don\'t call me Shirley.',
            ),
            array(
                ':one!one@1.2.3 PRIVMSG #test :wait a sec',
                "PRIVMSG #test :\001ACTION waits\001",
            ),
            array(
                ':one!one@1.2.3 PRIVMSG #test :wait a second',
                "PRIVMSG #test :\001ACTION waits\001",
            ),
            array(
                ':one!one@1.2.3 PRIVMSG #test :one sec',
                "PRIVMSG #test :\001ACTION gets out stopwatch\001",
            ),
            array(
                ':one!one@1.2.3 PRIVMSG unit :thanks',
                "/^PRIVMSG one :/",
            ),
            array(
                ':one!one@1.2.3 PRIVMSG unit :thank you',
                "/^PRIVMSG one :/",
            ),
            array(
                ':one!one@1.2.3 PRIVMSG #test :unit thanks',
                "/^PRIVMSG #test :/",
            ),
            array(
                ':one!one@1.2.3 PRIVMSG #test :thanks unit',
                "/^PRIVMSG #test :/",
            ),
            array(
                ':one!one@1.2.3 PRIVMSG #test :unit thank you',
                "/^PRIVMSG #test :/",
            ),
            array(
                ':one!one@1.2.3 PRIVMSG #test :thank you unit',
                "/^PRIVMSG #test :/",
            ),
            array(
                ':one!one@1.2.3 PRIVMSG #test :wonders what unit is up to...',
                "/^PRIVMSG #test :/",
            ),
        );
    }
}
