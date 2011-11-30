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
    const SECS_DAY      = 86400;
    const SECS_HOUR     = 3600;

    const JOIN        = 'join';
    const NICK        = 'nick';
    const PASS        = 'pass';
    const SERVER_PASS = 'serverpass';
    const USER        = 'user';

    private $lastMsg  = 0;


    /**
     * Message collections by user and channels (a user is a channel)
     *
     * @var ircMessage
     */
    private $channels;

    private $connected           = false;
    private $reconnect           = 1;
    private $reconnectwait       = 2;
    private $connectAttemptsMade = 0;
    private $connectAttempts     = 2;
    private $join;
    private $nicks;
    private $nick;
    private $pass;
    private $serverPass;
    /**
     * Socket Connection
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
     * @param ircSocket $socket        - tcp socket connection class.
     * @param ircClient $client        - client that called the connection.
     *
     * @return ircConnection
     */
    public function __construct($configuration, ircSocket $socket, $client=null)
    {
        $classVars = get_class_vars(get_class($this));
        foreach ($configuration as $item=>$value)
        {
            if (true === in_array($item, $classVars))
            {
                $this->$item = $value;
            }
        }
        $this->config               = $configuration;
        $this->client               = $client;
        $this->socket               = $socket;
        $this->user                 = $configuration[self::USER];
        $this->connected            = false;
        $this->connectAttemptsMade  = 0;
        $this->join                 = (false === empty($configuration[self::JOIN]))
                                      ? array_flip(explode(',', $configuration[self::JOIN]))
                                      : array();
        $this->nicks                = explode(',', $configuration[self::NICK]);
        $this->pass                 = (false === empty($configuration[self::PASS]))
                                      ? $configuration[self::PASS]
                                      : null;
        $this->serverPass           = (false === empty($configuration[self::SERVER_PASS]))
                                      ? $configuration[self::SERVER_PASS]
                                      : null;
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

    /**
     * Magic getter for private/protected properties
     *
     * @param string $name - property being accessed
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
     * Connect to the irc server and login
     *
     * @return boolean
     */
    private function connect()
    {
        while(false === $this->connected && $this->connectAttemptsMade <= $this->connectAttempts)
        {
            if (0 === $this->connectAttemptsMade)
            {
                $this->debugPrint('Connecting...');
                $this->connectAttemptsMade++;
                $this->connected = $this->socket->connect();
            }
            else if (false === $this->connected && 1 == $this->reconnect)
            {
                $this->debugPrint("Sleeping for $this->reconnectwait seconds...");
                sleep($this->reconnectwait);
                $this->connectAttemptsMade++;
                $this->connected = $this->socket->connect();
            }
            else if (0 == $this->reconnect || $this->connectAttemptsMade >= $this->connectAttempts)
            {
                return false;
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
            if (false === empty($this->join[$channel]))
            {
                unset($this->join[$channel]);
            }
            if (false === empty($this->channels[$channel]))
            {
                unset($this->channels[$channel]);
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
            $channels = array_flip($this->join);
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
            $this->join[$channel]   = true;
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
        $commands = array(
            'join',
            'leave',
            'part',
            'stats',
            'uptime',
            'kick',
            'version',
            'ping'
        );
        $commands = array_merge($commands, $this->getDiskCommands());
        foreach ($this->plugins as $plugin)
        {
            $commands = array_merge($commands, $plugin->getCommands());
        }
        $commands = array_flip(array_flip($commands));
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
            print(
                'Command path not found '.
                dirname(__FILE__).
                DIRECTORY_SEPARATOR.
                $this->config['commandpath']."\n"
            );
            return array();
        }
        $commands = array();
        $handle = opendir($path);
        while (false !== ($command = readdir($handle)))
        {
            if (true === is_file($path.DIRECTORY_SEPARATOR.$command) &&
                is_executable($path.DIRECTORY_SEPARATOR.$command))
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
     * @param ircMessage $message - message containing parameters for command
     *
     * @return void
     */
    private function executeDiskCommand(ircMessage $message)
    {
        $bits    = explode(' ', $message->message);
        $command = substr(array_shift($bits), 1);
        $path    = realpath(dirname(__FILE__).DIRECTORY_SEPARATOR.$this->config['commandpath']);
        if (false === $path)
        {
            print(
                'Command path not found '.
                dirname(__FILE__).
                DIRECTORY_SEPARATOR.
                $this->config['commandpath']."\n"
            );
        }
        $exec = $path.DIRECTORY_SEPARATOR.$command;
        $execPath = pathinfo($exec, PATHINFO_DIRNAME);
        $safePath = pathinfo($path.DIRECTORY_SEPARATOR.'.', PATHINFO_DIRNAME);
        if ($execPath !== $safePath)
        {
            print("$message->from attempted to call $exec\n");
            return;
        }

        /**
         * parameter order is
         *
         * nick
         * channel
         * sender
         * command + args
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
        $command = $exec.' '.
            escapeshellcmd($this->nick).' '.
            escapeshellcmd($channel).' '.
            escapeshellcmd($message->from).' '.
            escapeshellcmd($message->message).' 2>/dev/null';
        exec($command, $output, $return);

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
     * Format uptime as days hrs mins and secs
     *
     * @param int $starttime - timestamp to use to calculate the offset
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
        $this->writeline(
            "PRIVMSG $to :I have seen in the following number of messages per channel/user:"
        );
        foreach ($this->messageCount as $channel=>$count)
        {
            $this->writeline("PRIVMSG $to :$channel : $count messages");
        }
        $this->writeline("PRIVMSG $to :I have ".count($this->plugins).' plugins registered');
    }

    /**
     * Respond to a ping from the irc server.
     *
     * @param string $received - string PING text received
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
     * @param string $channel - channel to receive messages from
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
     * @param ircMessage $message - message to process
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
     * @param string $received - raw message string
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
     * @param string $string - string to be sent
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
     * @param mixed $message - message to be printed
     *
     * @return void
     */
    private function debugPrint($message)
    {
        $this->client->debugPrint($message, !empty($this->config['debug']));
    }
}