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
class welcomerTest extends PHPUnit_Framework_TestCase
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
            'enabled'  => 'true',
            'users'    => 'one,two,three'
        );
        if (PHP_DIR === '@@'."PHP_DIR".'@@')
        {
            $this->config['datafile'] = __DIR__.DIRECTORY_SEPARATOR.'fixtures'.DIRECTORY_SEPARATOR;
        }
        else
        {
            $this->config['datafile'] = '/tmp/';
        }


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
        $this->config['datafile'] = null;

        $plugin = new welcomer($this->client, $this->config);

        $keys = array_flip($plugin->getCommands());
        $this->assertArrayHasKey('welcome', $keys);
        $this->assertArrayHasKey('welcomeTopic', $keys);
    }

    /**
     * Test welcomer responses
     *
     * @param string $message   - message to respond to
     * @param mixed  $responses - expected repsonses
     *
     * @dataProvider responsesDataProvider
     * @group          dataprovider
     *
     * @return void
     */
    public function testResponses($message, $responses)
    {
        $this->config['datafile'] .= 'fixtures-responses';

        if (
            true === is_null($message) &&
            true === is_null($responses) &&
            true === file_exists($this->config['datafile'])
            )
        {
            unlink($this->config['datafile']);
            $this->assertFileNotExists(
                $this->config['datafile'],
                'fixtures not cleared'
            );
            return;
        }

        $plugin = new welcomer($this->client, $this->config);

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
                null,
                null,
            ),
            array(
                ':two!two@1.2.3 PRIVMSG #test :welcome',
                null,
            ),
            array(
                ':two!two@1.2.3 PRIVMSG #test :welcome one',
                null,
            ),
            array(
                ':two!two@1.2.3 PRIVMSG #test :unit welcome',
                null,
            ),
            array(
                ':two!two@1.2.3 PRIVMSG #test :unit welcome one',
                null,
            ),
            array(
                ':two!two@1.2.3 PRIVMSG #test :unit welcomeTopic welcome to the #test channel',
                null,
            ),
            array(
                ':two!two@1.2.3 PRIVMSG #test :welcome',
                null,
            ),
            array(
                ':two!two@1.2.3 PRIVMSG #test :welcome one',
                null,
            ),
            array(
                ':two!two@1.2.3 PRIVMSG #test :unit: welcome',
                'PRIVMSG #test :welcome to the #test channel',
            ),
            array(
                ':two!two@1.2.3 PRIVMSG #test :unit: welcome one',
                'PRIVMSG #test :one: welcome to the #test channel',
            ),
            array(
                ':one!one@1.2.3 JOIN :#test',
                'PRIVMSG #test :one: welcome to the #test channel',
            ),
            array(
                ':four!four@1.2.3 PRIVMSG #test :unit: welcome one two three',
                'PRIVMSG #test :one, two, three: welcome to the #test channel',
            ),
            array(
                ':four!four@1.2.3 PRIVMSG unit :welcomeTopic welcome to the #test channel',
                'PRIVMSG four :Sorry, you\'re not in my authorised users list.',
            ),
            array(
                ':four!four@1.2.3 PRIVMSG unit :welcome test',
                'PRIVMSG #test :four: welcome to the #test channel',
            ),
            array(
                ':four!four@1.2.3 PRIVMSG unit :stats',
                array(
                    'PRIVMSG four :I know of the following users per channel:',
                    'PRIVMSG four :#test : 1',
                )
            ),
            array(
                ':two!two@1.2.3 JOIN :#test',
                'PRIVMSG #test :two: welcome to the #test channel',
            ),
            array(
                ':two!two@1.2.3 JOIN :#test',
                null,
            ),
            array(
                ':four!four@1.2.3 PRIVMSG #test :unit: stats',
                array(
                    'PRIVMSG four :I know of the following users per channel:',
                    'PRIVMSG four :#test : 2',
                )
            ),
            array(
                ':four!four@1.2.3 PRIVMSG unit :welcome test one two three',
                'PRIVMSG #test :one, two, three: welcome to the #test channel',
            ),
            array(
                ':two!two@1.2.3 PRIVMSG #test :unit welcomeTopic none',
                null,
            ),
            array(
                ':two!two@1.2.3 PRIVMSG #test :unit welcome',
                null,
            ),
            array(
                ':two!two@1.2.3 PRIVMSG #test :unit welcome one',
                null,
            ),
            array(
                ':four!four@1.2.3 PRIVMSG unit :showWelcomers',
                array(
                    'PRIVMSG four :The following users have permissions to set topic messages:',
                    'PRIVMSG four :one, two, three.',
                ),
            ),
            array(
                null,
                null,
            ),
        );
    }

    /**
     * Test that adding topic
     *
     * @return void
     */
    public function testAddTopicChannel()
    {
        $this->config['datafile'] = null;

        $plugin = new welcomer($this->client, $this->config);

        $this->assertArrayNotHasKey('#test', $plugin->welcomes, 'before');

        $plugin->process(
            new ircMessage(
                ':one!one@1.2.3 PRIVMSG #test :unit: welcomeTopic welcome to this channel',
                'unit'
            )
        );

        $this->assertArrayHasKey('#test', $plugin->welcomes, 'during');

        $plugin->process(
            new ircMessage(
                ':one!one@1.2.3 PRIVMSG #test :unit: welcomeTopic none',
                'unit'
            )
        );

        $this->assertArrayNotHasKey('#test', $plugin->welcomes, 'after');
    }

    /**
     * Test that adding topic
     *
     * @return void
     */
    public function testAddTopicPrivate1()
    {
        $this->config['datafile'] = null;

        $plugin = new welcomer($this->client, $this->config);

        $this->assertArrayNotHasKey('#test', $plugin->welcomes, 'before');

        $plugin->process(
            new ircMessage(
                ':one!one@1.2.3 PRIVMSG unit :welcomeTopic #test welcome to this channel',
                'unit'
            )
        );

        $this->assertArrayHasKey('#test', $plugin->welcomes, 'during');

        $plugin->process(
            new ircMessage(
                ':one!one@1.2.3 PRIVMSG unit :welcomeTopic #test none',
                'unit'
            )
        );

        $this->assertArrayNotHasKey('#test', $plugin->welcomes, 'after');
    }
    /**

     * Test that adding topic
     *
     * @return void
     */
    public function testAddTopicPrivate2()
    {
        $this->config['datafile'] = null;

        $plugin = new welcomer($this->client, $this->config);

        $this->assertArrayNotHasKey('#test', $plugin->welcomes, 'before');

        $plugin->process(
            new ircMessage(
                ':one!one@1.2.3 PRIVMSG unit :welcomeTopic test welcome to this channel',
                'unit'
            )
        );

        $this->assertArrayHasKey('#test', $plugin->welcomes, 'during');

        $plugin->process(
            new ircMessage(
                ':one!one@1.2.3 PRIVMSG unit :welcomeTopic test none',
                'unit'
            )
        );

        $this->assertArrayNotHasKey('#test', $plugin->welcomes, 'after');
    }

    /**
     * Create a fixture set to be loaded by a test
     *
     * @param string   $file     - file to write fixture to
     * @param string[] $channels - hash map of channels and users seen
     * @param string[] $welcomes - hash map of channels' welcome messages
     *
     * @return void
     */
    private function createFixture($file, $channels, $welcomes)
    {
        if (false === is_dir($file))
        {
            $data = array(
                'channels' => $channels,
                'welcomes' => $welcomes,
                );
            file_put_contents($file, gzcompress(serialize($data)));
        }
    }
}
