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
use b3cft\IrcBot\IrcConnection,
    b3cft\CoreUtils\Registry;

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

/**
 *
 *
 * @author b3cft
 * @covers b3cft\IrcBot\IrcConnection
 *
 */
class IrcConnectionTest extends PHPUnit_Framework_TestCase
{

    private $client;
    private $socket;
    private $config = array(
            'helpurl'         => 'someurl',
            'nick'            => 'unittest',
            'user'            => 'unittest',
            'commandpath'     => '../../tests/fixtures',
            'connectAttempts' => 1,
            'reconnectwait'   => 0,
            'reconnect'          => 1,
        );

    /**
     * run before each test to reset mocks
     *
     * @return void
     */
    public function setUp()
    {
        $this->socket = $this->getMock(
            'b3cft\IrcBot\ircSocket',
            array('write', 'connect'), array(), '', false
        );
        $this->client = $this->getMock(
            'b3cft\IrcBot\ircBot',
            array('debugPrint'), array(), '', false
        );
        $this->config = array(
            'helpurl'         => 'someurl',
            'nick'            => 'unittest',
            'user'            => 'unittest',
            'commandpath'     => '../../tests/fixtures',
            'connectAttempts' => 2,
            'reconnectwait'   => 0,
            'reconnect'          => 1,
        );
    }

    /**
     * clean up after each test
     *
     * @return void
     */
    public function tearDown()
    {
        $this->socket = null;
        $this->client = null;
    }

    /**
     * test that magic __get function returns null for non existant properties
     *
     * @return void
     */
    public function testGetNotDefined()
    {
        $conn = new ircConnection($this->config, $this->socket, $this->client);

        $this->assertNull($conn->wibblewobblewoo);
    }

    /**
     * Check that basic connect works
     *
     * @return void
     */
    public function testConnectBasic()
    {
        $conn = new ircConnection($this->config, $this->socket, $this->client);
        $this->socket->expects($this->once())
            ->method('connect')
            ->will($this->returnValue(true));
        $this->assertInstanceOf('b3cft\IrcBot\ircConnection', $conn);
        $method = new ReflectionMethod('b3cft\IrcBot\ircConnection', 'connect');
        $method->setAccessible(true);
        $method->invoke($conn);
    }

    /**
     * Test that a retry occurs if retrying is enabled and first attempt fails
     *
     * @return void
     */
    public function testConnectRetry()
    {
        $conn = new ircConnection($this->config, $this->socket, $this->client);

        $this->socket->expects($this->at(0))
            ->method('connect')
            ->will($this->returnValue(false));

        $this->socket->expects($this->at(1))
            ->method('connect')
            ->will($this->returnValue(true));
        $this->assertInstanceOf('b3cft\IrcBot\ircConnection', $conn);
        $method = new ReflectionMethod('b3cft\IrcBot\ircConnection', 'connect');
        $method->setAccessible(true);
        $method->invoke($conn);
    }

    /**
     * Test that we only try once and fail if retrys are not enabled
     *
     * @return void
     */
    public function testConnectNoRetry()
    {
        $this->config['reconnect'] = 0;
        $conn = new ircConnection($this->config, $this->socket, $this->client);

        $this->assertEquals(0, $conn->reconnect);

        $this->socket->expects($this->once())
            ->method('connect')
            ->will($this->returnValue(false));

        $this->assertInstanceOf('b3cft\IrcBot\ircConnection', $conn);
        $method = new ReflectionMethod('b3cft\IrcBot\ircConnection', 'connect');
        $method->setAccessible(true);
        $method->invoke($conn);
    }

    /**
     * Test granting of ops
     *
     * @return void
     */
    public function testOp()
    {
        $conn = new ircConnection($this->config, $this->socket, $this->client);
        $this->socket->expects($this->once())
            ->method('write')
            ->with($this->equalTo('MODE #channel +o bob'));
        $this->assertInstanceOf('b3cft\IrcBot\ircConnection', $conn);

        $method = new ReflectionMethod('b3cft\IrcBot\ircConnection', 'op');
        $method->setAccessible(true);
        $method->invoke($conn, '#channel', array('bob'));
    }

    /**
     * Test revoking of ops
     *
     * @return void
     */
    public function testDeop()
    {
        $conn = new ircConnection($this->config, $this->socket, $this->client);
        $this->socket->expects($this->once())
            ->method('write')
            ->with($this->equalTo('MODE #channel -o bob'));
        $this->assertInstanceOf('b3cft\IrcBot\ircConnection', $conn);

        $method = new ReflectionMethod('b3cft\IrcBot\ircConnection', 'deop');
        $method->setAccessible(true);
        $method->invoke($conn, '#channel', array('bob'));
    }

    /**
     * Test granting of voice
     *
     * @return void
     */
    public function testVoice()
    {
        $conn = new ircConnection($this->config, $this->socket, $this->client);
        $this->socket->expects($this->once())
            ->method('write')
            ->with($this->equalTo('MODE #channel +v bob'));
        $this->assertInstanceOf('b3cft\IrcBot\ircConnection', $conn);

        $method = new ReflectionMethod('b3cft\IrcBot\ircConnection', 'voice');
        $method->setAccessible(true);
        $method->invoke($conn, '#channel', array('bob'));
    }

    /**
     * Test revoking of ops
     *
     * @return void
     */
    public function testDevoice()
    {
        $conn = new ircConnection($this->config, $this->socket, $this->client);
        $this->socket->expects($this->once())
            ->method('write')
            ->with($this->equalTo('MODE #channel -v bob'));
        $this->assertInstanceOf('b3cft\IrcBot\ircConnection', $conn);

        $method = new ReflectionMethod('b3cft\IrcBot\ircConnection', 'devoice');
        $method->setAccessible(true);
        $method->invoke($conn, '#channel', array('bob'));
    }

    /**
     * Test a given message triggers a response from the bot to the socket connection
     *
     * @param string $rawMsg      - raw message received
     * @param string $socketReply - responsed expected to be sent back to the socket
     *
     * @dataProvider commandDataProvider
     *
     * @return void
     */
    public function testCommands($rawMsg, $socketReply)
    {
        $conn = new ircConnection($this->config, $this->socket, $this->client);
        $msg  = new ircMessage($rawMsg, 'unittest');
        $this->socket->expects($this->once())
            ->method('write')
            ->with($this->equalTo($socketReply));
        $this->assertInstanceOf('b3cft\IrcBot\ircConnection', $conn);

        $method = new ReflectionMethod('b3cft\IrcBot\ircConnection', 'processMsg');
        $method->setAccessible(true);

        $method->invoke($conn, $msg);
    }

    /**
     * Data Provider for testCommands unit tests
     *
     * @return string[]
     */
    public function commandDataProvider()
    {
        return array(
            array(
            ':b3cft!b3cft@.IP PRIVMSG #frameworks :unittest: ?dosomething',
            'PRIVMSG #frameworks :command failed'
            ),
            array(
            ':b3cft!b3cft@.IP PRIVMSG #frameworks :unittest: join #frameworksdev',
            'JOIN #frameworksdev'
            ),
            array(
            ':b3cft!b3cft@.IP PRIVMSG #frameworks :unittest: join frameworksdev',
            'JOIN #frameworksdev'
            ),
            array(
            ':b3cft!b3cft@.IP PRIVMSG unittest :join #frameworksdev',
            'JOIN #frameworksdev'
            ),
            array(
            ':b3cft!b3cft@.IP PRIVMSG unittest :join frameworksdev',
            'JOIN #frameworksdev'
            ),
            array(
            ':b3cft!b3cft@.IP PRIVMSG #frameworks :unittest: voice',
            'MODE #frameworks +v b3cft'
            ),
            array(
            ':b3cft!b3cft@.IP PRIVMSG #frameworks :unittest: devoice',
            'MODE #frameworks -v b3cft'
            ),
            array(
            ':b3cft!b3cft@.IP PRIVMSG #frameworks :unittest: voice bob',
            'MODE #frameworks +v bob'
            ),
            array(
            ':b3cft!b3cft@.IP PRIVMSG #frameworks :unittest: devoice bob',
            'MODE #frameworks -v bob'
            ),
            array(
            ':b3cft!b3cft@.IP PRIVMSG unittest :voice #frameworksdev',
            'MODE #frameworksdev +v b3cft'
            ),
            array(
            ':b3cft!b3cft@.IP PRIVMSG unittest :devoice frameworksdev',
            'MODE #frameworksdev -v b3cft'
            ),
            array(
            ':b3cft!b3cft@.IP PRIVMSG unittest :voice #frameworksdev bob',
            'MODE #frameworksdev +v bob'
            ),
            array(
            ':b3cft!b3cft@.IP PRIVMSG unittest :devoice frameworksdev bob',
            'MODE #frameworksdev -v bob'
            ),
            array(
            ':b3cft!b3cft@.IP PRIVMSG #frameworks :unittest: leave',
            'PART #frameworks'
            ),
            array(
            ':b3cft!b3cft@.IP PRIVMSG #frameworks :unittest: leave #frameworksdev',
            'PART #frameworksdev'
            ),
            array(
            ':b3cft!b3cft@.IP PRIVMSG #frameworks :unittest: leave frameworksdev',
            'PART #frameworksdev'
            ),
            array(
            ':b3cft!b3cft@.IP PRIVMSG unittest :leave #frameworksdev',
            'PART #frameworksdev'
            ),
            array(
            ':b3cft!b3cft@.IP PRIVMSG unittest :leave frameworksdev',
            'PART #frameworksdev'
            ),
            array(
            ':b3cft!b3cft@.IP PRIVMSG #frameworks :unittest: part #frameworksdev',
            'PART #frameworksdev'
            ),
            array(
            ':b3cft!b3cft@.IP PRIVMSG #frameworks :unittest: part frameworksdev',
            'PART #frameworksdev'
            ),
            array(
            ':b3cft!b3cft@.IP PRIVMSG unittest :part #frameworksdev',
            'PART #frameworksdev'
            ),
            array(
            ':b3cft!b3cft@.IP PRIVMSG unittest :part frameworksdev',
            'PART #frameworksdev'
            ),
            array(
            ':b3cft!b3cft@.IP PRIVMSG unittest :ping',
            'PRIVMSG b3cft :PONG'
            ),
            array(
            ':b3cft!b3cft@.IP PRIVMSG #frameworks :unittest: ping',
            'PRIVMSG b3cft :PONG'
            ),
            array(
            ':b3cft!b3cft@.IP PRIVMSG unittest :version',
            'PRIVMSG b3cft :b3cft\'s phpbot Version @@PACKAGE_VERSION@@'
            ),
            array(
            ':b3cft!b3cft@.IP PRIVMSG #frameworks :unittest: version',
            'PRIVMSG b3cft :b3cft\'s phpbot Version @@PACKAGE_VERSION@@'
            ),
            array(
            ':b3cft!b3cft@.IP PRIVMSG unittest :help',
            'PRIVMSG b3cft :someurl'
            ),
            array(
            ':b3cft!b3cft@.IP PRIVMSG #frameworks :unittest: help',
            'PRIVMSG #frameworks :someurl'
            ),

        );
    }

    /**
     * Test a given message triggers a response from the bot to the socket connection
     *
     * @param string $rawMsg      - raw message sent
     * @param string $socketReply - socket reply expected
     *
     * @dataProvider commandFirstDataProvider
     *
     * @return void
     */
    public function testFirstLineCommands($rawMsg, $socketReply)
    {
        $conn = new ircConnection($this->config, $this->socket, $this->client);
        $msg  = new ircMessage($rawMsg, 'unittest');
        $this->socket->expects($this->at(0))
            ->method('write')
            ->with($this->equalTo($socketReply));
        $this->assertInstanceOf('b3cft\IrcBot\ircConnection', $conn);

        $method = new ReflectionMethod('b3cft\IrcBot\ircConnection', 'processMsg');
        $method->setAccessible(true);

        $method->invoke($conn, $msg);
    }

    /**
     *Data provider for testFirstLineCommands
     *
     * @return string[]
     */
    public function commandFirstDataProvider()
    {
        return array(
            array(
            ':b3cft!b3cft@.IP PRIVMSG #frameworks :unittest: uptime',
            'PRIVMSG b3cft :I have the following uptime record:'
            ),
            array(
            ':b3cft!b3cft@.IP PRIVMSG unittest :uptime',
            'PRIVMSG b3cft :I have the following uptime record:'
            ),
            array(
            ':b3cft!b3cft@.IP PRIVMSG #frameworks :unittest: stats',
            'PRIVMSG b3cft :I have the following uptime record:'
            ),
            array(
            ':b3cft!b3cft@.IP PRIVMSG unittest :stats',
            'PRIVMSG b3cft :I have the following uptime record:'
            ),
            array(
            ':b3cft!b3cft@.IP PRIVMSG #frameworks :unittest: commands',
            'PRIVMSG b3cft :devoice, join, kick, leave, part'
            ),
            array(
            ':b3cft!b3cft@.IP PRIVMSG unittest :commands',
            'PRIVMSG b3cft :devoice, join, kick, leave, part'
            ),
        );
    }

    /**
     * Test a given message triggers a response from the bot to the socket connection
     *
     * @param string   $rawMsg        - raw message received
     * @param string[] $socketReplies - expected socket replies
     *
     * @dataProvider commandMultiDataProvider
     *
     * @return void
     */
    public function testMultiLineCommands($rawMsg, array $socketReplies)
    {
        $conn = new ircConnection($this->config, $this->socket, $this->client);
        $msg  = new ircMessage($rawMsg, 'unittest');

        foreach ($socketReplies as $index=>$socketReply)
        {
            $this->socket->expects($this->at($index))
                ->method('write')
                ->with($this->equalTo($socketReply));
        }
        $this->assertInstanceOf('b3cft\IrcBot\ircConnection', $conn);

        $method = new ReflectionMethod('b3cft\IrcBot\ircConnection', 'processMsg');
        $method->setAccessible(true);

        $method->invoke($conn, $msg);
    }

    /**
     *Data provider for testMultiLineCommands
     *
     * @return string[]
     */
    public function commandMultiDataProvider()
    {
        return array(
            array(
            ':b3cft!b3cft@.IP PRIVMSG #frameworks :unittest: join one #two three',
            array(
                'JOIN #one',
                'JOIN #two',
                'JOIN #three',
            )
            ),
            array(
            ':b3cft!b3cft@.IP PRIVMSG unittest :join one #two three',
            array(
                'JOIN #one',
                'JOIN #two',
                'JOIN #three',
            )
            ),
            array(
            ':b3cft!b3cft@.IP PRIVMSG #frameworks :unittest: leave one #two #three',
            array(
                'PART #one',
                'PART #two',
                'PART #three',
            )
            ),
            array(
            ':b3cft!b3cft@.IP PRIVMSG unittest :leave #one two #three',
            array(
                'PART #one',
                'PART #two',
                'PART #three',
            )
            ),
            array(
            ':b3cft!b3cft@.IP PRIVMSG #frameworks :unittest: voice one two three',
            array(
                'MODE #frameworks +v one',
                'MODE #frameworks +v two',
                'MODE #frameworks +v three',
            )
            ),
            array(
            ':b3cft!b3cft@.IP PRIVMSG unittest :voice frameworks one two three',
            array(
                'MODE #frameworks +v one',
                'MODE #frameworks +v two',
                'MODE #frameworks +v three',
            )
            ),
        );
    }

    /**
     * Check that the client quits if we tell it to in a channel request
     *
     * @return void
     */
    public function testQuitInChannel()
    {
        $conn = new ircConnection($this->config, $this->socket, $this->client);
        $msg  = new ircMessage(
            ':b3cft!b3cft@.IP PRIVMSG #frameworks :unittest: time',
            'unittest'
        );
        $this->socket->expects($this->once())
            ->method('write')
            ->with($this->equalTo('PRIVMSG b3cft :'.date('Y-m-d H:i:s')));
        $this->assertInstanceOf('b3cft\IrcBot\ircConnection', $conn);

        $method = new ReflectionMethod('b3cft\IrcBot\ircConnection', 'processMsg');
        $method->setAccessible(true);

        $method->invoke($conn, $msg);
    }

    /**
     * Check that the client quits if we tell it to in a private message
     *
     * @return void
     */
    public function testQuitInPrivate()
    {
        $conn = new ircConnection($this->config, $this->socket, $this->client);
        $msg  = new ircMessage(
            ':b3cft!b3cft@.IP PRIVMSG unittest :time',
            'unittest'
        );
        $this->socket->expects($this->once())
            ->method('write')
            ->with($this->equalTo('PRIVMSG b3cft :'.date('Y-m-d H:i:s')));
        $this->assertInstanceOf('b3cft\IrcBot\ircConnection', $conn);

        $method = new ReflectionMethod('b3cft\IrcBot\ircConnection', 'processMsg');
        $method->setAccessible(true);

        $method->invoke($conn, $msg);

    }

    /**
     * Check that the client returns the time if we ask in a channel
     *
     * @return void
     */
    public function testTimeInChannel()
    {
        $conn = new ircConnection($this->config, $this->socket, $this->client);
        $msg  = new ircMessage(
            ':b3cft!b3cft@.IP PRIVMSG #frameworks :unittest: !quit',
            'unittest'
        );

        $this->assertInstanceOf('b3cft\IrcBot\ircConnection', $conn);

        $method = new ReflectionMethod('b3cft\IrcBot\ircConnection', 'processMsg');
        $method->setAccessible(true);

        $result = $method->invoke($conn, $msg);

        $this->assertFalse($result);
    }

    /**
     * check that the client returns the time if we ask in a private message
     *
     * @return void
     */
    public function testTimeInPrivate()
    {
        $conn = new ircConnection($this->config, $this->socket, $this->client);
        $msg  = new ircMessage(
            ':b3cft!b3cft@.IP PRIVMSG unittest :!quit',
            'unittest'
        );

        $this->assertInstanceOf('b3cft\IrcBot\ircConnection', $conn);

        $method = new ReflectionMethod('b3cft\IrcBot\ircConnection', 'processMsg');
        $method->setAccessible(true);

        $result = $method->invoke($conn, $msg);

        $this->assertFalse($result);
    }

    /**
     * Test ProcessMsg
     *
     * @return void
     */
    public function testProcessMsg()
    {
        $conn = new ircConnection($this->config, $this->socket, $this->client);
        $msg  = new ircMessage(
            ':b3cft!b3cft@.IP PRIVMSG #frameworks :unittest: voice',
            'unittest'
        );
        $this->socket->expects($this->any())
            ->method('write')
            ->with($this->equalTo('MODE #frameworks +v b3cft'));
        $this->assertInstanceOf('b3cft\IrcBot\ircConnection', $conn);

        $method = new ReflectionMethod('b3cft\IrcBot\ircConnection', 'processMsg');
        $method->setAccessible(true);

        $this->assertAttributeEmpty(
            'messageCount',
            $conn,
            'message count is not zero before calling'
        );
        $this->assertAttributeEmpty(
            'channels',
            $conn,
            'channels array is not empty before calling'
        );

        $method->invoke($conn, $msg);

        $this->assertAttributeNotEmpty(
            'messageCount',
            $conn,
            'message count is not incremented after processing a message'
        );
        $this->assertAttributeNotEmpty(
            'channels',
            $conn,
            'channels is not incremented after processing a message'
        );
        $this->assertArrayHasKey(
            '#frameworks',
            $conn->channels,
            'channel name not in channels array'
        );

        $this->assertEquals(1, $conn->messageCount['#frameworks']);

        $method->invoke($conn, $msg);

        $this->assertEquals(2, $conn->messageCount['#frameworks']);
    }

    /**
     * Test calling a disk command
     *
     * @return void
     */
    public function testProcessMsgDiskCommandInChannel()
    {
        $conn = new ircConnection($this->config, $this->socket, $this->client);
        $msg  = new ircMessage(
            ':b3cft!b3cft@.IP PRIVMSG #frameworks :unittest: ?dosomething',
            'unittest'
        );
        $this->socket->expects($this->once())
            ->method('write')
            ->with($this->equalTo('PRIVMSG #frameworks :command failed'));
        $this->assertInstanceOf('b3cft\IrcBot\ircConnection', $conn);

        $method = new ReflectionMethod('b3cft\IrcBot\ircConnection', 'processMsg');
        $method->setAccessible(true);

        $method->invoke($conn, $msg);
    }

    /**
     * Test calling a disk command
     *
     * @return void
     */
    public function testProcessMsgDiskCommandDirect()
    {
        $conn = new ircConnection($this->config, $this->socket, $this->client);
        $msg  = new ircMessage(
            ':b3cft!b3cft@.IP PRIVMSG unittest : ?dosomething',
            'unittest'
        );
        $this->socket->expects($this->once())
            ->method('write')
            ->with($this->equalTo('PRIVMSG b3cft :command failed'));
        $this->assertInstanceOf('b3cft\IrcBot\ircConnection', $conn);

        $method = new ReflectionMethod('b3cft\IrcBot\ircConnection', 'processMsg');
        $method->setAccessible(true);

        $method->invoke($conn, $msg);
    }

    /**
     * Test calling join with a missing parameter
     *
     * @return void
     */
    public function testProcessMsgJoinCommandFailed()
    {
        $conn = new ircConnection($this->config, $this->socket, $this->client);
        $msg  = new ircMessage(
            ':b3cft!b3cft@.IP PRIVMSG #frameworks :unittest: join',
            'unittest'
        );
        $this->socket->expects($this->once())
            ->method('write')
            ->with($this->equalTo('PRIVMSG b3cft :You need to tell me what channel to join!'));
        $this->assertInstanceOf('b3cft\IrcBot\ircConnection', $conn);

        $method = new ReflectionMethod('b3cft\IrcBot\ircConnection', 'processMsg');
        $method->setAccessible(true);

        $method->invoke($conn, $msg);
    }

    /**
     * Test calling join with a missing parameter
     *
     * @return void
     */
    public function testProcessMsgJoinCommandFailedDirect()
    {
        $conn = new ircConnection($this->config, $this->socket, $this->client);
        $msg  = new ircMessage(
            ':b3cft!b3cft@.IP PRIVMSG unittest :join',
            'unittest'
        );
        $this->socket->expects($this->once())
            ->method('write')
            ->with($this->equalTo('PRIVMSG b3cft :You need to tell me what channel to join!'));
        $this->assertInstanceOf('b3cft\IrcBot\ircConnection', $conn);

        $method = new ReflectionMethod('b3cft\IrcBot\ircConnection', 'processMsg');
        $method->setAccessible(true);

        $method->invoke($conn, $msg);
    }

    /**
     * Test calling join with a missing parameter
     *
     * @return void
     */
    public function testProcessMsgJoinCommand()
    {
        $conn = new ircConnection($this->config, $this->socket, $this->client);
        $msg  = new ircMessage(
            ':b3cft!b3cft@.IP PRIVMSG #frameworks :unittest: join frameworksdev',
            'unittest'
        );
        $this->socket->expects($this->once())
            ->method('write')
            ->with($this->equalTo('JOIN #frameworksdev'));
        $this->assertInstanceOf('b3cft\IrcBot\ircConnection', $conn);

        $method = new ReflectionMethod('b3cft\IrcBot\ircConnection', 'processMsg');
        $method->setAccessible(true);

        $method->invoke($conn, $msg);
    }

    /**
     * Check that plugins are called for messages when received.
     *
     * @return void
     */
    public function testPluginsProcessMsg()
    {
        $plugin = $this->getMock(
            'b3cft\IrcBot\ircPlugin',
            array('process', 'getCommands'),
            array(), '', false
        );
        $conn = new ircConnection($this->config, $this->socket, $this->client);
        $msg  = new ircMessage(
            ':b3cft!b3cft@.IP PRIVMSG unittest :time', 'unittest'
        );
        $this->assertInstanceOf('b3cft\IrcBot\ircConnection', $conn);
        $conn->registerPlugin($plugin);
        $plugin->expects($this->once())
            ->method('process')
            ->with($msg);

        $method = new ReflectionMethod('b3cft\IrcBot\ircConnection', 'processMsg');
        $method->setAccessible(true);

        $method->invoke($conn, $msg);
    }
}