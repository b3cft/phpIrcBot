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
    if (PHP_DIR !== '@@'.'PHP_DIR'.'@@')
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
class opsTest extends PHPUnit_Framework_TestCase
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
        $plugin = new ops($this->client, $this->config);

        $keys = array_flip($plugin->getCommands());
        $this->assertArrayHasKey('op', $keys);
        $this->assertArrayHasKey('deop', $keys);
        $this->assertArrayHasKey('addop', $keys);
        $this->assertArrayHasKey('delop', $keys);
        $this->assertArrayHasKey('showops', $keys);
    }

    /**
     * Test op and deop commands
     *
     * @param string $message   - message to respond to
     * @param mixed  $responses - expected repsonses
     *
     * @dataProvider responsesDataProvider
     *
     * @return void
     */
    public function testResponses($message, $responses)
    {
        $plugin = new ops($this->client, $this->config);

        if (true === is_null($responses))
        {
            $this->client->expects($this->never())
                ->method('writeline');
        }
        else if (is_string($responses))
        {
            $this->client->expects($this->once())
                ->method('writeline')
                ->with($responses);
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
                ':two!two@1.2.3 PRIVMSG #test :op',
                null,
            ),
            array(
                ':two!two@1.2.3 PRIVMSG #test :voice',
                null,
            ),
            array(
                ':two!two@1.2.3 PRIVMSG #test :unit: some other message',
                null,
            ),
            array(
                ':two!two@1.2.3 PRIVMSG #test :unit some other message',
                null,
            ),
            array(
                ':two!two@1.2.3 PRIVMSG #test :unit: op',
                'MODE #test +o two',
            ),
            array(
                ':two!two@1.2.3 PRIVMSG #test :unit: op one',
                'MODE #test +o one',
            ),
            array(
                ':two!two@1.2.3 PRIVMSG #test :unit: op four',
                'MODE #test +o four',
            ),
            array(
                ':two!two@1.2.3 PRIVMSG #test :unit: voice',
                'MODE #test +v two',
            ),
            array(
                ':two!two@1.2.3 PRIVMSG #test :unit: voice one',
                'MODE #test +v one',
            ),
            array(
                ':two!two@1.2.3 PRIVMSG #test :unit: voice four',
                'MODE #test +v four',
            ),
            array(
                ':four!four@1.2.3 PRIVMSG #test :unit: op',
                'PRIVMSG four :Sorry, you\'re not in my authorised users list.',
            ),
            array(
                ':four!four@1.2.3 PRIVMSG #test :unit: op one',
                'PRIVMSG four :Sorry, you\'re not in my authorised users list.',
            ),
            array(
                ':two!two@1.2.3 PRIVMSG unit :op #test',
                'MODE #test +o two',
            ),
            array(
                ':two!two@1.2.3 PRIVMSG unit :op test',
                'MODE #test +o two',
            ),
            array(
                ':two!two@1.2.3 PRIVMSG unit :op #test one',
                'MODE #test +o one',
            ),
            array(
                ':two!two@1.2.3 PRIVMSG unit :op test one',
                'MODE #test +o one',
            ),
            array(
                ':two!two@1.2.3 PRIVMSG unit :op #test four',
                'MODE #test +o four',
            ),
            array(
                ':two!two@1.2.3 PRIVMSG unit :op test four',
                'MODE #test +o four',
            ),
            array(
                ':two!two@1.2.3 PRIVMSG unit :voice #test',
                'MODE #test +v two',
            ),
            array(
                ':two!two@1.2.3 PRIVMSG unit :voice test',
                'MODE #test +v two',
            ),
            array(
                ':two!two@1.2.3 PRIVMSG unit :voice #test one',
                'MODE #test +v one',
            ),
            array(
                ':two!two@1.2.3 PRIVMSG unit :voice test one',
                'MODE #test +v one',
            ),
            array(
                ':two!two@1.2.3 PRIVMSG unit :voice #test four',
                'MODE #test +v four',
            ),
            array(
                ':two!two@1.2.3 PRIVMSG unit :voice test four',
                'MODE #test +v four',
            ),
            array(
                ':four!four@1.2.3 PRIVMSG unit :op #test',
                'PRIVMSG four :Sorry, you\'re not in my authorised users list.',
            ),
            array(
                ':four!four@1.2.3 PRIVMSG unit :op test',
                'PRIVMSG four :Sorry, you\'re not in my authorised users list.',
            ),
            array(
                ':four!four@1.2.3 PRIVMSG unit :op #test one',
                'PRIVMSG four :Sorry, you\'re not in my authorised users list.',
            ),
            array(
                ':four!four@1.2.3 PRIVMSG unit :op test one',
                'PRIVMSG four :Sorry, you\'re not in my authorised users list.',
            ),
            array(
                ':one!one@1.2.3 PRIVMSG #test :unit: op one two three',
                array(
                    'MODE #test +o one',
                    'MODE #test +o two',
                    'MODE #test +o three',
                ),
            ),
            array(
                ':one!one@1.2.3 PRIVMSG unit :op #test one two three',
                array(
                    'MODE #test +o one',
                    'MODE #test +o two',
                    'MODE #test +o three',
                ),
            ),
            array(
                ':one!one@1.2.3 PRIVMSG unit :op test one two three',
                array(
                    'MODE #test +o one',
                    'MODE #test +o two',
                    'MODE #test +o three',
                ),
            ),
            array(
                ':one!one@1.2.3 PRIVMSG #test :unit: voice one two three',
                array(
                    'MODE #test +v one',
                    'MODE #test +v two',
                    'MODE #test +v three',
                ),
            ),
            array(
                ':one!one@1.2.3 PRIVMSG unit :voice #test one two three',
                array(
                    'MODE #test +v one',
                    'MODE #test +v two',
                    'MODE #test +v three',
                ),
            ),
            array(
                ':one!one@1.2.3 PRIVMSG unit :voice test one two three',
                array(
                    'MODE #test +v one',
                    'MODE #test +v two',
                    'MODE #test +v three',
                ),
            ),
            array(
                ':two!two@1.2.3 PRIVMSG #test :deop',
                null,
            ),
            array(
                ':two!two@1.2.3 PRIVMSG #test :unit: some other message',
                null,
            ),
            array(
                ':two!two@1.2.3 PRIVMSG #test :unit some other message',
                null,
            ),
            array(
                ':two!two@1.2.3 PRIVMSG #test :unit: deop',
                'MODE #test -o two',
            ),
            array(
                ':two!two@1.2.3 PRIVMSG #test :unit: deop one',
                'MODE #test -o one',
            ),
            array(
                ':two!two@1.2.3 PRIVMSG #test :unit: deop four',
                'MODE #test -o four',
            ),
            array(
                ':two!two@1.2.3 PRIVMSG #test :unit: devoice',
                'MODE #test -v two',
            ),
            array(
                ':two!two@1.2.3 PRIVMSG #test :unit: devoice one',
                'MODE #test -v one',
            ),
            array(
                ':two!two@1.2.3 PRIVMSG #test :unit: devoice four',
                'MODE #test -v four',
            ),
            array(
                ':four!four@1.2.3 PRIVMSG #test :unit: deop',
                'PRIVMSG four :Sorry, you\'re not in my authorised users list.',
            ),
            array(
                ':four!four@1.2.3 PRIVMSG #test :unit: deop one',
                'PRIVMSG four :Sorry, you\'re not in my authorised users list.',
            ),
            array(
                ':four!four@1.2.3 PRIVMSG #test :unit: devoice',
                'PRIVMSG four :Sorry, you\'re not in my authorised users list.',
            ),
            array(
                ':four!four@1.2.3 PRIVMSG #test :unit: devoice one',
                'PRIVMSG four :Sorry, you\'re not in my authorised users list.',
            ),
            array(
                ':two!two@1.2.3 PRIVMSG unit :deop #test',
                'MODE #test -o two',
            ),
            array(
                ':two!two@1.2.3 PRIVMSG unit :deop test',
                'MODE #test -o two',
            ),
            array(
                ':two!two@1.2.3 PRIVMSG unit :deop #test one',
                'MODE #test -o one',
            ),
            array(
                ':two!two@1.2.3 PRIVMSG unit :deop test one',
                'MODE #test -o one',
            ),
            array(
                ':two!two@1.2.3 PRIVMSG unit :deop #test four',
                'MODE #test -o four',
            ),
            array(
                ':two!two@1.2.3 PRIVMSG unit :deop test four',
                'MODE #test -o four',
            ),
            array(
                ':two!two@1.2.3 PRIVMSG unit :devoice #test',
                'MODE #test -v two',
            ),
            array(
                ':two!two@1.2.3 PRIVMSG unit :devoice test',
                'MODE #test -v two',
            ),
            array(
                ':two!two@1.2.3 PRIVMSG unit :devoice #test one',
                'MODE #test -v one',
            ),
            array(
                ':two!two@1.2.3 PRIVMSG unit :devoice test one',
                'MODE #test -v one',
            ),
            array(
                ':two!two@1.2.3 PRIVMSG unit :devoice #test four',
                'MODE #test -v four',
            ),
            array(
                ':two!two@1.2.3 PRIVMSG unit :devoice test four',
                'MODE #test -v four',
            ),
            array(
                ':four!four@1.2.3 PRIVMSG unit :deop #test',
                'PRIVMSG four :Sorry, you\'re not in my authorised users list.',
            ),
            array(
                ':four!four@1.2.3 PRIVMSG unit :deop test',
                'PRIVMSG four :Sorry, you\'re not in my authorised users list.',
            ),
            array(
                ':four!four@1.2.3 PRIVMSG unit :deop #test one',
                'PRIVMSG four :Sorry, you\'re not in my authorised users list.',
            ),
            array(
                ':four!four@1.2.3 PRIVMSG unit :deop test one',
                'PRIVMSG four :Sorry, you\'re not in my authorised users list.',
            ),
            array(
                ':one!one@1.2.3 PRIVMSG #test :unit: deop one two three',
                array(
                    'MODE #test -o one',
                    'MODE #test -o two',
                    'MODE #test -o three',
                ),
            ),
            array(
                ':one!one@1.2.3 PRIVMSG unit :deop #test one two three',
                array(
                    'MODE #test -o one',
                    'MODE #test -o two',
                    'MODE #test -o three',
                ),
            ),
            array(
                ':one!one@1.2.3 PRIVMSG unit :deop test one two three',
                array(
                    'MODE #test -o one',
                    'MODE #test -o two',
                    'MODE #test -o three',
                ),
            ),
            array(
                ':one!one@1.2.3 PRIVMSG #test :unit: devoice one two three',
                array(
                    'MODE #test -v one',
                    'MODE #test -v two',
                    'MODE #test -v three',
                ),
            ),
            array(
                ':one!one@1.2.3 PRIVMSG unit :devoice #test one two three',
                array(
                    'MODE #test -v one',
                    'MODE #test -v two',
                    'MODE #test -v three',
                ),
            ),
            array(
                ':one!one@1.2.3 PRIVMSG unit :devoice test one two three',
                array(
                    'MODE #test -v one',
                    'MODE #test -v two',
                    'MODE #test -v three',
                ),
            ),
        );
    }

    /**
     * Test that adding a new user to the ops list works
     *
     * @return void
     */
    public function testAddOpChannel()
    {
        $plugin = new ops($this->client, $this->config);

        $this->assertArrayNotHasKey('four', $plugin->authUsers);

        $plugin->process(new ircMessage(':one!one@1.2.3 PRIVMSG #test :unit: addop four', 'unit'));

        $this->assertArrayHasKey('four', $plugin->authUsers);
    }

    /**
     * Test that adding a new user to the ops list works
     *
     * @return void
     */
    public function testAddOpPrivateMessage()
    {
        $plugin = new ops($this->client, $this->config);

        $this->assertArrayNotHasKey('four', $plugin->authUsers);

        $plugin->process(new ircMessage(':one!one@1.2.3 PRIVMSG unit :addop four', 'unit'));

        $this->assertArrayHasKey('four', $plugin->authUsers);
    }

    /**
     * Test that removing a new user to the ops list works
     *
     * @return void
     */
    public function testDelOpChannel()
    {
        $plugin = new ops($this->client, $this->config);

        $this->assertArrayHasKey('two', $plugin->authUsers);

        $plugin->process(new ircMessage(':one!one@1.2.3 PRIVMSG #test :unit: delop two', 'unit'));

        $this->assertArrayNotHasKey('two', $plugin->authUsers);
    }

    /**
     * Test that removing a new user to the ops list works
     *
     * @return void
     */
    public function testDelOpPrivateMessage()
    {
        $plugin = new ops($this->client, $this->config);

        $this->assertArrayHasKey('two', $plugin->authUsers);

        $plugin->process(new ircMessage(':one!one@1.2.3 PRIVMSG unit :delop two', 'unit'));

        $this->assertArrayNotHasKey('two', $plugin->authUsers);
    }

    /**
     * Test that bot will show list of authorised users
     *
     * @return void
     */
    public function testShowOps()
    {
        $plugin = new ops($this->client, $this->config);

        $this->client->expects($this->at(0))
            ->method('writeline')
            ->with('PRIVMSG one :The following users have permissions to give/take ops:');

        $this->client->expects($this->at(1))
            ->method('writeline')
            ->with('PRIVMSG one :one, two, three.');

        $plugin->process(new ircMessage(':one!one@1.2.3 PRIVMSG #test :unit: showops', 'unit'));

    }
}
