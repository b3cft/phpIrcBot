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

use b3cft\IrcBot\ircMessage;
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
    const SECS_DAY      = 86400;
    const SECS_HOUR     = 3600;

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
    private $reconnectwait      = 30;
    private $connectRetriesMade = 0;
    private $connectRetries     = 2;
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
            : array();
        $this->nicks         = explode(',', $configuration[self::NICK]);
        $this->pass          = (false === empty($configuration[self::PASS]))
            ? $configuration[self::PASS]
            : null;
        $this->serverPass    = (false === empty($configuration[self::SERVER_PASS]))
            ? $configuration[self::SERVER_PASS]
            : null;
        $this->user          = $configuration[self::USER];
        $this->uptime['bot uptime'] = time();
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
        while(false === $this->connected)
        {
            $this->debugPrint('Connecting...');
            $this->connected = $this->socket->connect();
            if (false === $this->connected && true === $this->reconnect && $this->connectRetriesMade < $this->connectRetries)
            {
                $this->debugPrint("Sleeping for $this->reconnectwait seconds...");
                sleep($this->reconnectwait);
                $this->connectRetriesMade++;
                $this->connected = $this->socket->connect();
            }
            else if ($this->connectRetriesMade >= $this->connectRetries)
            {
                die('I give up. Out of connection attempts');
            }
            if (true === $this->connected && false === $this->login())
            {
                $this->connected = false;
            }
        }
        $this->uptime['connection uptime'] = time();
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
            $status = $this->writeline("NICK {$this->nicks[$nickIndex]}");
            if (false === $status)
            {
                $this->debugPrint('Connection failed...');
                return false;
            }
            if ('' === ($received = $this->readline()))
            {
                if (false === $received)
                {
                    $this->debugPrint('Connection failed...');
                    return false;
                }

                $this->nick = $this->nicks[$nickIndex];
                $this->debugPrint("Sending USER {$this->user}...");
                $status = $this->writeline("USER {$this->user} 0 * :php scripted bot by b3cft");
                if (false === $status)
                {
                    $this->debugPrint('Connection failed...');
                    return false;
                }
                break;
            }
            $nickIndex++;
        }
        $this->join();
        return true;
    }

    /**
     * Leave on or more IRC channels
     *
     * @param string[] $channels - channels to leave
     *
     * @return void
     */
    private function leave($channels)
    {
        foreach ($channels as $channel)
        {
            if ('#' !== substr($channel, 0, 1))
            {
                $channel = '#'.$channel;
            }
            $key = array_search($channel, $this->join);
            if (false !== $key)
            {
                unset($this->join[$key]);
            }
            $this->writeline("PART $channel");
        }
    }

    /**
     * Add a new plugin to the listeners list
     *
     * @param ircPlugin $plugin - plugin object to be added
     *
     * @return void
     */
    public function registerPlugin(ircPlugin $plugin)
    {
        $this->plugins[] = $plugin;
    }

    /**
     * Join one or more channels
     *
     * @param string[] $channel - channels to join
     *
     * @return void
     */
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
            if (false === in_array($channel, $this->join))
            {
                $this->join[] = $channel;
            }
            $this->uptime[$channel] = time();
            $this->writeline("JOIN $channel");
        }

    }

    /**
     * Send a list of commands available to an IRC user
     *
     * @param string $to - irc user to send list of commands to
     *
     * @return void
     */
    private function getCommands($to)
    {
        $commands = array('join', 'leave', 'voice', 'devoice', 'part', 'stats', 'uptime', 'kick', 'part', 'version', 'ping');
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

    /**
     * Scan the command path folder for commands executable by users
     *
     * @return string[]
     */
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

    /**
     * Execute a command from the command path folder and echo the response back to the user or
     * channel.
     *
     * @param ircMessage $message
     *
     * @return void
     */
    private function executeDiskCommand(ircMessage $message)
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
	    exec("$exec '$this->nick' '$channel' '$message->from' $message->message 2>/dev/null", $output, $return);
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

    /**
     * Grant operator status to a user
     *
     * @param string   $channel - channel to change user mode on
     * @param string[] $users   - users to change mode
     *
     * @return void
     */
    private function op($channel, $users)
    {
        $this->mode($channel, '+o', $users);
    }

    /**
     * Remove operator status from users
     *
     * @param string   $channel - channel to change user mode on
     * @param string[] $users   - users to change mode
     *
     * @return void
     */
    private function deop($channel, $users)
    {
        $this->mode($channel, '-o', $users);
    }

    /**
     * Grant voice status from users
     *
     * @param string   $channel - channel to change user mode on
     * @param string[] $users   - users to change mode
     *
     * @return void
     */
    private function voice($channel, $users)
    {
        $this->mode($channel, '+v', $users);
    }

    /**
     * Remove voice status from users
     *
     * @param string   $channel - channel to change user mode on
     * @param string[] $users   - users to change mode
     *
     * @return void
     */
    private function devoice($channel, $users)
    {
        $this->mode($channel, '-v', $users);
    }

    /**
     * Add or remove status from users
     *
     * @param string   $channel - channel to change user mode on
     * @param string   $mode    - mode to change
     * @param string[] $users   - users to change mode
     *
     * @return void
     */
    private function mode($channel, $mode, $users)
    {
        if ('#' !== substr($channel, 0, 1))
        {
            $channel = '#'.$channel;
        }
        foreach ($users as $user)
        {
            $this->writeline("MODE $channel $mode $user");
        }
    }

    /**
     * Format uptime as days hrs mins and secs
     *
     * @param int $starttime
     *
     * @return string
     */
    private function formatUptime($starttime)
    {
        $delta  = time()-$starttime;
        $days   = floor($delta/self::SECS_DAY);
        $remain = $delta - $days * self::SECS_DAY;
        $hours  = floor($remain/self::SECS_HOUR);
        $remain = $remain - $hours * self::SECS_HOUR;
        $mins   = floor($remain/60);
        $secs   = $remain - $mins * 60;
        return " $days days, $hours hours, $mins mins, $secs secs";
    }

    /**
     * Display uptime stats to a user
     *
     * @param unknown_type $to - user to display stats to
     *
     * @return void
     */
    private function uptime($to)
    {
        $this->writeline("PRIVMSG $to :I have the following uptime record:");
        foreach ($this->uptime as $channel=>$uptime)
        {
            $this->writeline("PRIVMSG $to :$channel : ".$this->formatUptime($uptime));
        }
    }

    /**
     * Display statistics to a user who requests them
     *
     * @param string $to - user to display stats to
     *
     * @return void
     */
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

    /**
     * Respond to a ping from the irc server.
     *
     * @return void
     */
    private function pong($received)
    {
        $this->writeline('PONG :'.substr($received, 6));
    }

    /**
     * Retrieve the current messages heard in a channel
     *
     * @param string $channel
     *
     * @return ircMessage[]
     */
    public function getMessageStack($channel)
    {
        if (false === empty($this->channels[$channel]))
        {
            return $this->channels[$channel];
        }
        return array();
    }

    /**
     * Process a message and handle it and/or pass it on to plugin to be handled
     *
     * @param ircMessage $message
     *
     * @return void
     */
    private function processMsg(ircMessage $message)
    {
        if ('PRIVMSG' === $message->action)
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
        }

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
                        $response = 'You need to tell me what channel to join!';
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
                    $response = "b3cft's phpbot Version @@PACKAGE_VERSION@@";
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
                    else if (1 === count($params))
                    {
                        $this->$command($params[0], array($message->from));
                    }
                    else if(2 <= count($params))
                    {
                        $this->$command($params[0], array_slice($params, 1));
                    }
                break;

                case 'commands':
                    $this->getCommands($message->from);
                break;

                case 'help':
                    $replyTo  = $message->channel;
                    $response = $this->config['helpurl'];
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

    /**
     * Retrieve the raw irc message and decide wether to process it or not.
     * Also handle ping/pong messages here.
     *
     * @param string $received
     *
     * @return boolean
     */
    private function talkIRC($received)
    {
        $return = true;
        if('PING :' === substr($received, 0, 6))
        {
           $this->pong($received);
        }
        else if (0 !== preg_match('/(PRIVMSG|JOIN|PART|MODE)/', $received))
        {
            $return = $this->processMsg(new ircMessage($received, $this->nick));
        }
        return (false === $return) ? false : true;
    }

    /**
     * Run the connection, looping until the connection dies are we are asked to quit.
     *
     * @return void
     */
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

    /**
     * Send a string back to the irc server via the socket
     *
     * @param string $string
     *
     * @return boolean
     */
    public function writeline($string)
    {
        $status = $this->socket->write($string);
        $this->debugPrint('S '.$string);
        return $status;
    }

    /**
     * Read a line from the socket
     *
     * @return string|boolean
     */
    private function readline()
    {
        $string = $this->socket->read();
        if (false === empty($string))
        {
            $this->debugPrint('R '.$string);
        }
        return $string;
    }

    /**
     * Pass a debug message to be printed back to the controller.
     *
     * @param mixed $message
     *
     * @return void
     */
    private function debugPrint($message)
    {
        $this->client->debugPrint($message, !empty($this->config['debug']));
    }
}