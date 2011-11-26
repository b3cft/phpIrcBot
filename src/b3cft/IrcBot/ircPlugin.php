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
 * @subpackage
 * @author     Andy 'Bob' Brockhurst, <andy.brockhurst@b3cft.com>
 * @license    http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @link       http://github.com/b3cft/
 * @version    @@PACKAGE_VERSION@@
 */

use b3cft\IrcBot\ircPlugin;
namespace b3cft\IrcBot;
use b3cft\IrcBot\ircConnection,
    b3cft\IrcBot\ircMessage;

abstract class ircPlugin
{

    /**
     * Configuration properties for the plugin
     *
     * @var string[]
     */
    protected $config;

    /**
     * Connection to irc server
     *
     * @var ircConnection
     */
    protected $client;

    /**
     * List of authorised users
     *
     * @var string[]
     */
    protected $authUsers = array();

    /**
     * Constructor
     *
     * @param ircConnection $client - ircConnection to be used for communication
     * @param array         $config - configuration array for this plugin
     *
     * @return ircPlugin
     */
    public function __construct(ircConnection $client, array $config)
    {
        $this->client = $client;
        $this->config = $config;
        if (false === empty($this->config['users']))
        {
            $this->authUsers = array_flip(explode(',', $config['users']));
        }
    }

    /**
     * Returns true if the user is in the list of authorised users.
     *
     * @param string $user - user being queried
     *
     * @return boolean
     */
    protected function isAuthorised($user)
    {
        if (false === isset($this->authUsers[$user]))
        {
            $this->client->writeline(
                "PRIVMSG $user :Sorry, you're not in my authorised users list."
            );
            return false;
        }
        else
        {
            return true;
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

    /**
     * Called to process each message when it is recieved
     *
     * @param ircMessage $message - irc message to be processed
     *
     * @return void
     */
    public abstract function process(ircMessage $message);

    /**
     * Returns a list of commands supported by the plugin, if any
     *
     * @return string[]
     */
    public abstract function getCommands();
}