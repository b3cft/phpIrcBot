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

class responses extends b3cft\IrcBot\ircPlugin
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
        if ('PRIVMSG' === $message->action)
        {
            if (true === $message->isToMe)
            {
                if (0 !== preg_match('/thank(?:s|\s*you)/i', $message->message))
                {
                    $this->thanks($message);
                }
            }
            else if (0 !== preg_match('/thank(?:s|\s*you)\s+'.$message->nick.'/i', $message->message))
            {
                $this->thanks($message);
            }
            else if (0 !== preg_match('/(?:\s|^)surely\W?/i', $message->message))
            {
                $this->client->writeline(
                    "PRIVMSG $message->channel :$message->from: Please don't call me Shirley."
                );
            }
            else if (0 !== preg_match(
                '/^(?:can\s+you)?\s*(?:wait|hang\s+on)\s+a\s+(mo(?:ment)?|sec(?:ond)?|min(?:ute)?)\W*$/i',
                 $message->message
                ))
            {
                $this->client->writeline(
                   "PRIVMSG $message->channel :\001ACTION waits\001"
                );
            }
            else if (0 !== preg_match(
                '/^(?:1|one)\s+(mo(?:ment)?|sec(?:ond)?|min(?:ute)?)\W*$/i',
                 $message->message
                ))
            {
                $this->client->writeline(
                   "PRIVMSG $message->channel :\001ACTION gets out stopwatch\001"
                );
            }
        }
    }

    /**
     * Send a thank you response back to the channel.
     *
     * @return void
     */
    private function thanks($message)
    {
        $msg = array(
            "\001ACTION curtsies\001",
            "\001ACTION bows\001",
            "\001ACTION blushes\001",
            "\001ACTION flutters eyelashes\001",
            "\001ACTION looks coyly over shoulder\001",
            "always a pleasure",
            "no problem",
        );
        $this->client->writeline("PRIVMSG $message->channel :".$msg[rand(0,count($msg)-1)]);
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
