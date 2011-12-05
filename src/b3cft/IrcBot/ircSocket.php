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
 * @link       http://github.com/b3cft
 * @version    @@PACKAGE_VERSION@@
 */

namespace b3cft\IrcBot;
use b3cft\CoreUtils\Registry;

/**
 * IrcBot implementation in PHP
 *
 * @category   PHP
 * @package    b3cft
 * @subpackage IrcBot
 * @author     Andy 'Bob' Brockhurst, <andy.brockhurst@b3cft.com>
 * @license    http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @link       http://github.com/b3cft/phpIRCBot
 */
class ircSocket
{

    const PORT        = 'port';
    const SERVER      = 'server';

    private $socket;
    private $server;
    private $port;

    /**
     * Constructor. Initialised socket connection and assigned connection parameters.
     *
     * @param string $server - server or ip to connect to
     * @param int    $port   - port on which to connect on
     *
     * @return ircSocket
     */
    public function __construct($server, $port)
    {
        $this->server = $server;
        $this->port   = $port;
    }

    /**
     * Create the socket connection to the server.
     *
     * @return void
     */
    private function create()
    {
        if (false === is_resource($this->socket))
        {
            $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        }
    }

    /**
     * Connect to the socket
     *
     * @return boolean
     */
    public function connect()
    {
        $this->disconnect();
        usleep(5000);
        $this->create();
        return @socket_connect($this->socket, $this->server, $this->port);
    }

    /**
     * Disconnect from the socket
     *
     * @return void
     */
    public function disconnect()
    {
        if (true === is_resource($this->socket))
        {
            socket_set_block($this->socket);
            socket_close($this->socket);
            $this->socket=null;
        }
    }

    /**
     * Make sure resource is release on socket deletion
     *
     * @return void
     */
    public function __destruct()
    {
        $this->disconnect();
        $this->socket = null;
    }

    /**
     * Read a line from the socket
     *
     * @return string|false
     */
    public function read()
    {
        $string = @socket_read($this->socket, 1024, PHP_NORMAL_READ);
        if (false !== $string)
        {
            $string = trim($string, " \t\n\r\0\x0B");
        }
        return $string;
    }

    /**
     * Write a line to the socket
     *
     * @param string $string - string to be written
     *
     * @return boolean
     */
    public function write($string)
    {
        return @socket_write($this->socket, $string."\n");
    }
}