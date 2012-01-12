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

/**
 * Pluging to ircbot that welcomes users to a channel the first time the bot sees them.
 * Users permitted to update the welcomeTopic.
 *
 * @category   PHP
 * @package    b3cft
 * @subpackage IrcBot
 * @author     Andy 'Bob' Brockhurst, <andy.brockhurst@b3cft.com>
 * @license    http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @link       http://github.com/b3cft/phpIRCBot
 */
class welcomer extends b3cft\IrcBot\ircPlugin
{

    protected $channels = array();
    protected $welcomes = array();

    /**
     * Constructor, do normal construct, then retreive state from disk
     *
     * @param ircConnection $client - ircConnection client for communication
     * @param mixed[]       $config - configuration for this plugin
     *
     * @return welcomer
     */
    public function __construct(ircConnection $client, array $config)
    {
        parent::__construct($client, $config);
        $this->retrieveData();
    }

    /**
     * Persist state to disk on destruct
     *
     * @return void
     */
    public function __destruct()
    {
        $this->persistData();
    }

    /**
     * Retrieve state from disk
     *
     * @return void
     */
    private function retrieveData()
    {
        if (false !== realpath($this->config['datafile']) &&
            true === is_file($this->config['datafile']) &&
            true === is_readable($this->config['datafile'])
            )
        {
            $file = file_get_contents($this->config['datafile']);
            $data = unserialize(gzuncompress($file));
            foreach ($data as $key=>$values)
            {
                $this->$key = $values;
            }
        }
    }

    /**
     * Persist state to disk
     *
     * @return void
     */
    private function persistData()
    {
        if (false === is_null($this->config['datafile']) &&
            false === is_dir($this->config['datafile'])
            )
        {
            $data = array(
                'channels' => $this->channels,
                'welcomes' => $this->welcomes,
                );
            file_put_contents($this->config['datafile'], gzcompress(serialize($data)));
        }
    }

    /**
     * Process a message
     *
     * @param ircMessage $message - message to process
     *
     * @return void
     */
    public function process(ircMessage $message)
    {

        if ('JOIN' === $message->action &&
            $message->from !== $message->nick &&
            true === empty($this->channels[$message->channel][trim($message->from, '_')]))
        {
            $this->channels[$message->channel][trim($message->from, '_')] = $message->time;
            $this->welcome($message->channel, $message->from);
            $this->persistData();
        }
        else if ('PRIVMSG' === $message->action && true === $message->isToMe)
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
                case 'welcome':
                    if (0 === count($params) && true === $message->isInChannel)
                    {
                        $this->addWelcome($message->channel, '');
                    }
                    else if (1 <= count($params) && true === $message->isInChannel)
                    {
                        $this->welcome($message->channel, implode(', ', $params));
                    }
                    else if (1 === count($params))
                    {
                        $this->welcome($params[0], $message->from);
                    }
                    else if (1 < count($params))
                    {
                        $this->welcome($params[0], implode(', ', array_slice($params, 1)));
                    }
                break;

                case 'stats':
                    $this->stats($message->from);
                break;

                case 'welcometopic':
                    if (true === $this->isAuthorised($message->from))
                    {
                        if (true === $message->isInChannel)
                        {
                            $channel = $message->channel;
                        }
                        else
                        {
                            $channel = array_shift($params);
                        }
                        $welcome = implode(' ', $params);
                        $this->addWelcome($channel, $welcome);
                    }
                break;

                case 'showwelcomers':
                    $ops = implode(', ', array_keys($this->authUsers));
                    $this->client->writeline(
                        "PRIVMSG $message->from :".
                        'The following users have permissions to set topic messages:'
                    );
                    $this->client->writeline("PRIVMSG $message->from :$ops.");
                break;
            }
        }
    }

    /**
     * Deliver stats as direct message to requestor
     *
     * @param string $replyTo - user who requested stats
     *
     * @return void
     */
    private function stats($replyTo)
    {
        $this->client->writeline("PRIVMSG $replyTo :I know of the following users per channel:");
        foreach ($this->channels as $channel=>$users)
        {
            $this->client->writeline("PRIVMSG $replyTo :$channel : ".count($users));
        }
    }

    /**
     * Welcome a user to a channel
     *
     * @param string $channel - channel to welcome user to
     * @param string $user    - user to welcome
     *
     * @return void
     */
    private function welcome($channel, $user)
    {
        if ('#' !== substr($channel, 0, 1))
        {
            $channel = '#'.$channel;
        }
        if (false === empty($this->welcomes[$channel]))
        {
            $this->client->writeline("PRIVMSG $channel :$user: {$this->welcomes[$channel]}");
        }


    }

    /**
     * Update the welcome to a channel message. send 'none' to blank
     *
     * @param string $channel - channel to update
     * @param string $welcome - message to set welcome to, none to blank message
     *
     * @return return_type
     */
    private function addWelcome($channel, $welcome)
    {
        if ('#' !== substr($channel, 0, 1))
        {
            $channel = '#'.$channel;
        }
        if ('none' === strtolower($welcome))
        {
            unset($this->welcomes[$channel]);
        }
        else if (false === empty($welcome))
        {
            $this->welcomes[$channel] = $welcome;
            $this->persistData();
        }
        else if (false === empty($this->welcomes[$channel]))
        {
            $this->client->writeline("PRIVMSG $channel :{$this->welcomes[$channel]}");
        }
    }

    /**
     * Return a list of commands the plugin responds to
     *
     * @return string[]
     */
    public function getCommands()
    {
        return array('welcomeTopic', 'welcome');
    }
}
