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

use b3cft\IrcBot\IrcSocket;

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
 */
class ircSocketTest extends PHPUnit_Framework_TestCase
{

    /**
     * Test socket creation
     *
     * @return void
     */
    public function testCreate()
    {
        $socket = new ircSocket('127.0.0.1', rand(32560, 65535));
        $this->assertInstanceOf('b3cft\IrcBot\ircSocket', $socket);

        $method = new ReflectionMethod('b3cft\IrcBot\ircSocket', 'create');
        $method->setAccessible(true);
        $method->invoke($socket);

        $this->assertAttributeNotEmpty('socket', $socket);
    }

    /**
     * Test connection fails with nothing listening
     *
     * @return void
     */
    public function testConnect()
    {
        $socket = new ircSocket('127.0.0.1', rand(32560, 65535));
        $this->assertInstanceOf('b3cft\IrcBot\ircSocket', $socket);

        $method = new ReflectionMethod('b3cft\IrcBot\ircSocket', 'connect');
        $method->setAccessible(true);
        $result = $method->invoke($socket);

        $this->assertFalse($result);

    }

    /**
     * Test reading from a socket and writing after a successful socket connect
     *
     * @return void
     */
    public function testSocketRead()
    {
        $port = rand(32560, 65535);
        $pid = pcntl_fork();
        if ($pid == -1)
        {
            $this->fail('Stopping, could not Fork');
        }
        elseif(!$pid)
        {
            /* I am a child I'll listen, then die */
            $this->socketServer('127.0.0.1', $port);
            exit(0);
        }
        else
        {
            /* I am the parent, I'll run the tests */
            $socket = new ircSocket('127.0.0.1', $port);
            $this->assertInstanceOf('b3cft\IrcBot\ircSocket', $socket);

            $method = new ReflectionMethod('b3cft\IrcBot\ircSocket', 'connect');
            $method->setAccessible(true);
            $result = $method->invoke($socket);

            $this->assertTrue($result);
            $this->assertEquals('hello world', $socket->read());
            $this->assertEquals(true, $socket->write('stop'));

        }

        pcntl_waitpid($pid, $status);
    }

    /**
     * Create a socket server that write's hello world on connection and shutdown after
     * receiving a stop message
     *
     * @param string  $address - address to listen on
     * @param integer $port    - tcp port to listen on
     *
     * @return void
     */
    private function socketServer($address, $port)
    {
        if (($sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) === false)
        {
            $this->fail("socket_create() failed: reason: " . socket_strerror(socket_last_error()));
        }

        if (socket_bind($sock, $address, $port) === false)
        {
            $this->fail(
                "socket_bind() failed: reason: " . socket_strerror(socket_last_error($sock))
            );
        }

        if (socket_listen($sock, 5) === false)
        {
            $this->fail(
                "socket_listen() failed: reason: " . socket_strerror(socket_last_error($sock))
            );
        }
        do
        {
            if (($msgsock = socket_accept($sock)) === false)
            {
                $this->fail(
                    "socket_accept() failed: reason: " . socket_strerror(socket_last_error($sock))
                );
                break;
            }
            $msg = "hello world\n";
            socket_write($msgsock, $msg, strlen($msg));
            do {
                if (false === ($buf = socket_read($msgsock, 2048, PHP_NORMAL_READ))) {
                    $this->fail(
                        "socket_read() failed: reason: ".
                        socket_strerror(socket_last_error($msgsock)).
                        "\n"
                    );
                    break 2;
                }
                if (!$buf = trim($buf)) {
                    continue;
                }
                if ($buf == 'stop') {
                    socket_close($msgsock);
                    break 2;
                }
            } while (true);
        }
        while(true);
        socket_close($sock);
    }
}