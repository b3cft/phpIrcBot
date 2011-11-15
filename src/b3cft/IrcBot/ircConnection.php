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
class ircConnection
{
    const CONN_TIMEOUT  = 180;
    const MAX_STACK_LEN = 200;

    const JOIN        = 'join';
    const NICK        = 'nick';
    const PASS        = 'pass';
    const SERVER_PASS = 'serverpass';
    const USER        = 'user';

    private $stack    = array();
    private $lastMsg  = 0;

    private $listeners = array();

    private $disconnect         = false;

    /**
     * Message collections by user and channels (a user is a channel)
     *
     * @var ircMessage
     */
    private $channels;

    private $connected          = false;
    private $reconnect          = true;
    private $reconnectwait      = 300;
    private $connectRetriesMade = 0;
    private $connectRetries     = 5;
    private $join;
    private $nicks;
    private $nick;
    private $pass;
    private $serverPass;
    /**
     * Socket Connecion
     *
     * @var ircSocket
     */
    private $socket;
    private $user;
    private $config;
    private $client;

    /**
     * Plugins registered.
     *
     * @var ircPlugin[]
     */
    private $plugins = array();

    private $uptime       = array();
    private $messageCount = array();

    /**
     * Constructor. Initialised socket connection and assigned connection parameters.
     *
     * @param mixed[]   $configuration - connection parameters for irc server.
     * @param ircClient $client        - client that called the connection.
     *
     * @return ircConnection
     */
    public function __construct($configuration, ircSocket $socket, $client=null)
    {
        $this->config        = $configuration;
        $this->client        = $client;
        $this->socket        = $socket;
        $this->join          = (false === empty($configuration[self::JOIN]))
            ? explode(',', $configuration[self::JOIN])
            : null;
        $this->nicks         = explode(',', $configuration[self::NICK]);
        $this->pass          = (false === empty($configuration[self::PASS]))
            ? $configuration[self::PASS]
            : null;
        $this->serverPass    = (false === empty($configuration[self::SERVER_PASS]))
            ? $configuration[self::SERVER_PASS]
            : null;
        $this->user          = $configuration[self::USER];
        $this->uptime['connection'] = time();
    }

    /**
     * Destructor, tidies up socket connections.
     *
     * @return void
     */
    public function __destruct()
    {
        $this->disconnect();
    }

    public function __get($name)
    {
        if (true === isset($this->$name))
        {
            return $this->$name;
        }
    }

    /**
     * Connect to the irc server and login
     *
     * @return void
     */
    private function connect()
    {
        if (false === $this->connected)
        {
            $this->debugPrint('Connecting...');
            $this->connected = $this->socket->connect();
            if (false === $this->connected && true === $this->reconnect && $this->connectRetriesMade < $this->connectRetries) {
                sleep($this->reconnectwait);
                $this->connectRetriesMade++;
                $this->connected = $this->socket->connect();
            }
            $this->login();
        }
        return $this->connected;
    }

    /**
     * Disconnect from the irc server.
     *
     * @return void
     */
    private function disconnect()
    {
        $this->socket->disconnect();
        if (true === $this->connected)
        {
            $this->connected = false;
        }
    }

    /**
     * Perform an irc login and nick assignment
     *
     * @return void
     */
    private function login()
    {
        $this->debugPrint('Logging in...');
        $noticeFound = false;
        // wait for a message from the server.
        while ('' != ($received = $this->readline()))
        {
            if (false === $noticeFound && false !== stripos($received, 'NOTICE AUTH :'))
            {
                $this->debugPrint('Notice found...');
                $noticeFound = true;
                break;
            }
        }
        $this->debugPrint('Outside the loop...');
        $nickIndex = 0;
        $loggedIn  = false;
        while($nickIndex < count($this->nicks))
        {
            $this->debugPrint("Sending NICK {$this->nicks[$nickIndex]}...");
            $this->writeline("NICK {$this->nicks[$nickIndex]}");
            if ('' === ($received = $this->readline()))
            {
                $this->nick = $this->nicks[$nickIndex];
                $this->debugPrint("Sending USER {$this->user}...");
                $this->writeline("USER {$this->user} 0 * :php scripted bot by b3cft");
                break;
            }
            $nickIndex++;
        }
        $this->join();
    }

    private function leave($channels)
    {
        foreach ($channels as $channel)
        {
            if ('#' !== substr($channel, 0, 1))
            {
                $channel = '#'.$channel;
            }
            $this->writeline("PART $channel");
        }
    }


    public function registerPlugin(ircPlugin $plugin)
    {
        $this->plugins[] = $plugin;
    }

    private function join($channel = null)
    {
        if (true === is_null($channel))
        {
            $channels = $this->join;
        }
        else
        {
            $channels = $channel;
        }

        foreach ($channels as $channel)
        {
            if ('#' !== substr($channel, 0, 1))
            {
                $channel = '#'.$channel;
            }
            $this->uptime[$channel] = time();
            $this->writeline("JOIN $channel");
        }

    }

    private function getCommands($to)
    {
        $commands = array('join', 'leave', 'voice', 'devoice', 'part', 'stats', 'uptime', 'kick', 'part', 'version', 'ping', '!quit');
        $commands = array_merge($commands, $this->getDiskCommands());
        foreach ($this->plugins as $plugin)
        {
            $commands = array_merge($commands, $plugin->getCommands());
        }
        sort($commands);

        for ($i = 0, $max=count($commands); $i<$max; $i=$i+5)
        {
            $cmds = array_slice($commands, $i, 5);
            $this->writeline("PRIVMSG $to :".implode(', ', $cmds));
        }
    }

    private function getDiskCommands()
    {
        $path = realpath(dirname(__FILE__).DIRECTORY_SEPARATOR.$this->config['commandpath']);
        if (false === $path)
        {
            print('Command path not found '.dirname(__FILE__).DIRECTORY_SEPARATOR.$this->config['commandpath']."\n");
            return array();
        }
        $commands = array();
        $handle = opendir($path);
    	while (false !== ($command = readdir($handle)))
    	{
    		if (true === is_file($path.DIRECTORY_SEPARATOR.$command) && is_executable($path.DIRECTORY_SEPARATOR.$command))
    		{
    	        $commands[] = '?'.pathinfo($path.DIRECTORY_SEPARATOR.$command, PATHINFO_FILENAME);
    		}
    	}
    	return $commands;
    }

    private function executeDiskCommand($message)
    {
        $bits    = explode(' ', $message->message);
        $command = substr(array_shift($bits),1);
        $path    = realpath(dirname(__FILE__).DIRECTORY_SEPARATOR.$this->config['commandpath']);
        if (false === $path)
        {
            print('Command path not found '.dirname(__FILE__).DIRECTORY_SEPARATOR.$this->config['commandpath']."\n");
        }
        $exec = $path.DIRECTORY_SEPARATOR.$command;
        if (pathinfo($exec, PATHINFO_DIRNAME) !== pathinfo($path.DIRECTORY_SEPARATOR.'.', PATHINFO_DIRNAME))
        {
            print("$message->from attempted to call $exec\n");
            return;
        }

	    /**
	     * $input = $_SERVER['argv'][1];
         * $toks = explode(" ",$input);
         * $nick = array_shift($toks);
         * $channel = array_shift($toks);
         * $sender = array_shift($toks);
         * $first = array_shift($toks);
	     */
        $channel = $message->channel;
        $from    = $message->from;
        $to      = $message->from;

	    if ($channel == $from)
	    {
	        $channel = 'null';
	    }
	    else
	    {
	        $to = $message->channel;
	    }
	    exec("$exec '$this->nick' '$channel' '$message->from' $message->message", $output, $return);
        if (0 !== $return)
        {
            $this->writeline("PRIVMSG $to :command failed");
        }
        else
        {
            foreach($output as $line)
            {
                $this->writeline("PRIVMSG $to :$line");
            }
		}
    }

    private function op($channel, $users)
    {
        $this->mode($channel, '+o', $users);
    }

    private function deop($channel, $users)
    {
        $this->mode($channel, '-o', $users);
    }

    private function voice($channel, $users)
    {
        $this->mode($channel, '+v', $users);
    }

    private function devoice($channel, $users)
    {
        $this->mode($channel, '-v', $users);
    }

    private function mode($channel, $mode, $users)
    {
        foreach ($users as $user)
        {
            $this->writeline("MODE $channel $mode $user");
        }
    }


    private function uptime($to)
    {
        $this->writeline("PRIVMSG $to :I have been in the following channels for:");
        foreach ($this->uptime as $channel=>$uptime)
        {
            $this->writeline("PRIVMSG $to :$channel : ".(time()-$uptime)."sec\n");
        }
    }

    private function stats($to)
    {
        $this->uptime($to);
        $this->writeline("PRIVMSG $to :I have seen in the following number of messages per channel/user:");
        foreach ($this->messageCount as $channel=>$count)
        {
            $this->writeline("PRIVMSG $to :$channel : $count messages");
        }
        $this->writeline("PRIVMSG $to :I have ".count($this->plugins).' plugins registered');
    }

    private function send()
    {

    }

    /**
     * Respond to a ping from the irc server.
     *
     * @return void
     */
    private function pong($received)
    {
        $this->writeline('PONG :'.substr($received, 6));
    }

    public function getMessageStack($channel)
    {
        if (false === empty($this->channels[$channel]))
        {
            return $this->channels[$channel];
        }
        return array();
    }

    private function processMsg(ircMessage $message)
    {
        $this->channels[$message->channel][] = $message;
        if (true === empty($this->messageCount[$message->channel]))
        {
            $this->messageCount[$message->channel] = 1;
        }
        else
        {
            $this->messageCount[$message->channel]++;
        }
        $this->lastMsg = time();

        foreach ($this->plugins as $plugin)
        {
            $plugin->process($message);
        }

        if ('?' === substr($message->message, 0, 1))
        {
            $this->executeDiskCommand($message);
        }
        else if (true === $message->isToMe)
        {
            $replyTo  = $message->from;
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
                case 'join':
                    if (0 === count($params))
                    {
                        $message = 'You need to tell me what channel to join!';
                    }
                    else
                    {
                        $this->join($params);
                    }
                break;

                case 'leave':
                case 'part':
                    if (0 === count($params))
                    {
                        $this->leave(array($message->channel));
                    }
                    else
                    {
                        $this->leave($params);
                    }
                break;

                case "ping":
                   $response = "PONG";
                break;

                case "time":
                    $response = date('Y-m-d H:i:s');
                break;

                case "version":
                    $response = "b3cft's phpbot Version @@PACKAGE_VERSION@@\x01";
                break;

                //case 'op':
                //case 'deop':
                case 'voice':
                case 'devoice':
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
                        $this->$command($params[0], array_slice($params, 1));
                    }
                break;

                case 'commands':
                    $this->getCommands($message->from);
                break;

                case 'kick':
                break;

                case 'uptime':
                    $this->uptime($message->from);
                break;

                case 'stats':
                    $this->stats($message->from);
                break;

                case '!quit':
                    return false;
                break;
            }
            if (false === empty($response))
            {
                $this->writeline("PRIVMSG $replyTo :$response");
            }
        }
    }

    private function talkIRC($received)
    {
        $return = true;
        if('PING :' === substr($received, 0, 6))
        {
           $this->pong($received);
        }
        else if (false !== strpos($received, 'PRIVMSG'))
        {
            $return = $this->processMsg(new ircMessage($received, $this));
        }
        return (false === $return) ? false : true;
    }

    public function run()
    {
        while (true === $this->connect())
        {
            while(false !== ($read = $this->readline()))
            {
                if (false === $this->talkIRC($read))
                {
                    $this->debugPrint('Breaking out of loop...');
                    // if we return a false, we were order to die.
                    break(2);
                }
            }
            $this->debugPrint('Retrying connection in loop...');
            $this->connected = false;
        }
        $this->debugPrint('Ending execution...');
        $this->writeline('QUIT : Ordered to quit! See ya.');
        $this->disconnect();
    }

    public function writeline($string)
    {
        $this->socket->write($string);
        $this->debugPrint('S '.$string);
        return $string;
    }

    private function readline()
    {
        $string = $this->socket->read();
        if (false === empty($string))
        {
            $this->debugPrint('R '.$string);
        }
        return $string;
    }

    private function debugPrint($message)
    {
        $this->client->debugPrint($message, !empty($this->config['debug']));
    }
}