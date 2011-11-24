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

class subber extends b3cft\IrcBot\ircPlugin
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
        if ('PRIVMSG' === $message->action && 5 < strlen($message->message))
        {
            if ('s/' === substr($message->message, 0, 2))
            {
                if (1 === preg_match('/^s\/([^\/]+)\/([^\/]*)(\/?)$/', $message->message, $matches))
                {
                    $this->sub($message, $matches);
                }
            }
            else if ('^' === substr($message->message, 0, 1))
            {
                if (1 === preg_match('/\^([^\^]+)\^(.*)$/', $message->message, $matches))
                {
                    $this->sub($message, $matches);
                }
            }
        }
    }

    /**
     * apply a substitution on a message
     *
     * @param ircMessage $message - message containing command to substitute
     * @param string[]   $matches - search and replace strings
     *
     * @return void
     */
    private function sub($message, $matches)
    {
        $stack = $this->client->getMessageStack($message->channel);
        while($msg = array_pop($stack))
        {
            if($msg->message === $message->message ||
                's/' === substr($msg->message, 0, 2) ||
                '^' === substr($msg->message, 0, 1))
            {
                continue;
            }
            $newMessage = preg_replace('/'.$matches[1].'/', $matches[2], $msg->message, -1, $count);
            if (0 < $count)
            {
                if ($msg->from === $message->from)
                {
                    $this->client->writeline(
                        "PRIVMSG $message->channel :$message->from: $newMessage"
                    );
                }
                else
                {
                    $this->client->writeline(
                        "PRIVMSG $message->channel :$message->from -> $msg->from: $newMessage"
                    );
                }
                break;
            }
        }
        if (true === isset($matches[3]) && '' === $matches[3])
        {
            $this->client->writeline(
                "PRIVMSG $message->channel :oh, and $message->from ".
                'you need to work on your regular expession syntax.'
            );
        }
    }

    /**
     * Return a list of commands the plugin responds to
     *
     * @return string[]
     */
    public function getCommands()
    {
        return array();
    }
}