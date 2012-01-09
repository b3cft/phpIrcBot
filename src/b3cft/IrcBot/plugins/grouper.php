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

class grouper extends b3cft\IrcBot\ircPlugin
{

    protected $groups = array();

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
                'groups' => $this->groups,
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
        $command = trim($command, ':;,? ');
        if ('PRIVMSG' === $message->action && true === $message->isToMe)
        {
            if ('group' === $command && true === empty($params) && false === empty($this->groups))
            {
                $groups = implode(', ', array_keys($this->groups));
                $this->client->writeline("PRIVMSG $message->channel :Groups available: $groups");
            }
            else if (
                'group' === $command &&
                false === empty($params) &&
                1 === count($params) &&
                false === empty($this->groups[$params[0]])
                )
            {
                unset($this->groups[$params[0]]);
                $this->persistData();
            }
            else if ('group' === $command && false === empty($params))
            {
                $group = array_shift($params);
                array_walk(
                    $params,
                    function(&$item, $key)
                    {
                        $item = trim($item, ':, ');
                    }
                );
                $this->groups[$group] = $params;
                $this->persistData();
            }
        }
        else if ('PRIVMSG' === $message->action && false === empty($this->groups[$command]))
        {
            $group = implode(', ', $this->groups[$command]);
            $this->client->writeline("PRIVMSG $message->channel :^^ $group");
        }
    }

    /**
     * Return a list of commands the plugin responds to
     *
     * @return string[]
     */
    public function getCommands()
    {
        return array('group');
    }
}