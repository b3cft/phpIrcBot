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

/* Include PSR0 Autoloader and add dev path to search */
use b3cft\IrcBot\ircMessage;
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
class subberTest extends PHPUnit_Framework_TestCase
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
        $plugin = new subber($this->client, $this->config);

        $this->assertEmpty($plugin->getCommands());
    }

    /**
     * Test substitution challenge responses
     *
     * @param ircMessage[] $stack
     * @param string       $message
     * @param mixed        $response
     *
     * @dataProvider responsesDataProvider
     *
     * @return void
     */
    public function testResponses($stack, $message, $responses)
    {
        $plugin = new subber($this->client, $this->config);

        $this->client->expects($this->any())
            ->method('getMessageStack')
            ->will($this->returnValue($stack));

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
                $this->client->expects($this->at($index+1))
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
        $stack = array(
            new ircMessage(':one!one@1.2.3 PRIVMSG #test :hello world', 'unit'),
            new ircMessage(':two!two@1.2.3 PRIVMSG #test :goodbye cruel world', 'unit'),
            new ircMessage(':two!two@1.2.3 PRIVMSG #test :hat stand', 'unit'),
            new ircMessage(':two!two@1.2.3 PRIVMSG #test :s/hat/glove/', 'unit'),
            new ircMessage(':two!two@1.2.3 PRIVMSG #test :^hat^glove', 'unit'),
        );

        return array(
            array(
                $stack,
                ':two!two@1.2.3 PRIVMSG #test :some other message',
                null,
            ),
            array(
                $stack,
                ':two!two@1.2.3 PRIVMSG #test :s/hat/glove/',
                'PRIVMSG #test :two: glove stand',
            ),
            array(
                $stack,
                ':two!two@1.2.3 PRIVMSG #test :s/wibble/wobble/',
                null,
            ),
            array(
                $stack,
                ':two!two@1.2.3 PRIVMSG #test :^wibble^wobble',
                null,
            ),
            array(
                $stack,
                ':two!two@1.2.3 PRIVMSG #test :^hat^glove',
                'PRIVMSG #test :two: glove stand',
            ),
            array(
                $stack,
                ':one!one@1.2.3 PRIVMSG #test :s/hat/glove/',
                'PRIVMSG #test :one -> two: glove stand',
            ),
            array(
                $stack,
                ':one!one@1.2.3 PRIVMSG #test :^hat^glove',
                'PRIVMSG #test :one -> two: glove stand',
            ),
            array(
                $stack,
                ':one!one@1.2.3 PRIVMSG #test :s/hat/glove',
                array(
                    'PRIVMSG #test :one -> two: glove stand',
                	'PRIVMSG #test :oh, and one, you need to work on your regular expression syntax.',
                ),
            ),
            array(
                $stack,
                ':two!two@1.2.3 PRIVMSG #test :s/hat/glove',
                array(
                    'PRIVMSG #test :two: glove stand',
                	'PRIVMSG #test :oh, and two, you need to work on your regular expression syntax.',
                ),
            ),

        );

    }
}