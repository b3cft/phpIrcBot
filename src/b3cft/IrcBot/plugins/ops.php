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

use b3cft\IrcBot\ircMessage,
    b3cft\IrcBot\ircConnection;

class ops extends b3cft\IrcBot\ircPlugin
{

    /**
     * Process a message
     *
     * @param ircMessage $message - message to process
     *
     * @return void
     */
    public function process(ircMessage $message)
    {
        if ('PRIVMSG' === $message->action && true === $message->isToMe)
        {
            $messageParts = explode(' ', $message->message);
            if (1 < count($messageParts))
            {
                $command = $messageParts[0];
                $params  = array_slice($messageParts, 1);
            }
            else
            {
                $command = $message->message;
                $params  = array();
            }
            switch (strtolower($command))
            {
                case 'op':
                case 'deop':
                    if (true === $this->isAuthorised($message->from))
                    {
                        if (0 === count($params) && true === $message->isInChannel)
                        {
                            $this->$command($message->channel, array($message->from));
                        }
                        else if(true === $message->isInChannel)
                        {
                            $this->$command($message->channel, $params);
                        }
                        else if(2 <= count($params))
                        {
                            if ('#' === substr($params[0], 0, 1))
                            {
                                $channel = $params[0];
                            }
                            else
                            {
                                $channel = '#'.$params[0];
                            }
                            $this->$command($channel, array_slice($params, 1));
                        }
                        else if (1 === count($params))
                        {
                            if ('#' === substr($params[0], 0, 1))
                            {
                                $channel = $params[0];
                            }
                            else
                            {
                                $channel = '#'.$params[0];
                            }
                            $this->$command($channel, array($message->from));
                        }
                    }
                break;

                case 'addop':
                    if (true === $this->isAuthorised($message->from))
                    {
                        $this->addOp($params);
                    }
                break;

                case 'delop':
                    if (true === $this->isAuthorised($message->from))
                    {
                        $this->delOp($params);
                    }
                break;

                case 'showops':
                    $ops = implode(', ', array_keys($this->authUsers));
                    $this->client->writeline(
                        "PRIVMSG $message->from : ".
                        'The following users have permissions to give/take ops:'
                    );
                    $this->client->writeline("PRIVMSG $message->from : $ops.");
                break;
            }
        }
    }

    /**
     * Add a user to the list of permitted operators
     *
     * @param string[] $users - users to add to ops list
     *
     * @return void
     */
    private function addOp(array $users)
    {
        foreach ($users as $user)
        {
            $this->authUsers[$user] = 1;
        }
    }

    /**
     * Remove users from the list of permitted operators
     *
     * @param string[] $users - users to remove from ops list
     *
     * @return void
     */
    private function delOp(array $users)
    {
        foreach ($users as $user)
        {
            unset($this->authUsers[$user]);
        }
    }

    /**
     * Attempt to grant ops to users in channel
     *
     * @param string   $channel - channel to grant user ops to
     * @param string[] $users   - array of users to grant ops to
     *
     * @return void
     */
    private function op($channel, $users)
    {
        $this->mode($channel, '+o', $users);
    }

    /**
     * Attempt to remove ops from users in channel
     *
     * @param string   $channel - channel to remove user ops from
     * @param string[] $users   - array of users to remove ops from
     *
     * @return void
     */
    private function deop($channel, $users)
    {
        $this->mode($channel, '-o', $users);
    }

    /**
     * Make mode changes to users in channel
     *
     * @param string   $channel - channel to make mode change to
     * @param string   $mode    - mode change to make
     * @param string[] $users   - users to make change to
     *
     * @return void
     */
    private function mode($channel, $mode, $users)
    {
        foreach ($users as $user)
        {
            $this->client->writeline("MODE $channel $mode $user");
        }
    }

    /**
     * Return a list of commands the plugin responds to
     *
     * @return string[]
     */
    public function getCommands()
    {
        return array('op', 'deop', 'addop', 'delop', 'showops');
    }
}