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
class ircMessage
{
    private $channel;
    private $from;
    private $to;
    private $message;
    private $raw;
    private $action;
    private $isToMe;
    private $isInChannel;
    private $time;
    private $nick;

    private static $watchedCommands = array(
        'PRIVMSG'=> true,
        'JOIN'   => true,
        'PART'     => true,
        'MODE'     => true,
    );

    /**
     * Constructor. Initialised socket connection and assigned connection parameters.
     *
     * @param srtring $raw  - raw message for processing.
     * @param string  $nick - nick of current bot.
     *
     * @return ircConnection
     */
    public function __construct($raw, $nick)
    {
        $this->nick    = $nick;
        $this->raw     = $raw;
        $this->time    = time();
        $bits          = explode(' ', $raw);
        if (3 <= count($bits) && false === empty($this::$watchedCommands[$bits[1]]))
        {
            $this->from    = substr($bits[0], 1, strpos($bits[0], '!', 3)-1);
            $this->action  = $bits[1];
            switch ($this->action)
            {
                case 'MODE':
                    $this->channel     = $bits[2];
                    $this->to          = isset($bits[4]) ? $bits[4] : '';
                    $this->isInChannel = true;
                break;

                case 'JOIN':
                case 'PART':
                    $this->isInChannel = true;
                    $this->channel     = substr($bits[2], 1);
                break;

                case 'PRIVMSG':
                    $this->to      = $bits[2];
                    $this->message = trim(
                        substr(implode(' ', array_slice($bits, 3)), 1),
                        " \t\n\r\0\x0B"
                    );
                    if ('#' === substr($this->to, 0, 1))
                    {
                        $this->channel     = $this->to;
                        $this->isInChannel = true;
                        $nick = $this->nick;
                        if (1 === preg_match("/^$nick:?\s+(.*)$/", $this->message, $match))
                        {
                            $this->message = trim($match[1]);
                            $this->isToMe  = true;
                        }
                        else
                        {
                            $this->isToMe = false;
                        }
                    }
                    else
                    {
                        $this->channel     = $this->from;
                        $this->isInChannel = false;
                        $this->isToMe      = true;
                    }

                break;
            }
        }
    }

    /**
     * Magic method to retrieve values of private or protected properties
     *
     * @param string $name - property being requested
     *
     * @return mixed
     */
    public function __get($name)
    {
        if (true === isset($this->$name))
        {
            return $this->$name;
        }
    }
}
