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
class IrcBot
{

    const PARAM_CONFIG_FILE = 'configfile';
    private static $instance;
    public static $testing = false;

    /**
     * Container for config utility
     *
     * @var b3cft\CoreUtils\Config
     */
    private $config;

    /**
     * Array of irc connections to maintain
     *
     * @var ircConnection[]
     */
    private $connectionList = array();

    /**
     * Private constructor, use getInstance() to get IrcBot objectß
     *
     * @return IrcBot
     */
    private function __construct()
    {
    }

    /**
     * Singleton retrieve instance function
     *
     * @return IrcBot
     */
    public static function getInstance()
    {
        if (true === empty(self::$instance) || true === self::$testing)
        {
            self::$instance = new IrcBot();
        }
        return self::$instance;
    }

    /**
     * Initialise IrcBot
     *
     * @param mixed $params - assoc array containing addition configuration information
     *
     * @return void
     */
    public function init($params = array())
    {
        $this->config = Registry::getInstance()->retrieve('Config');
        if (false === empty($params[self::PARAM_CONFIG_FILE]))
        {
            $config = $params[self::PARAM_CONFIG_FILE];
        }
        else if (true === defined('DEFAULT_CONFIG') && false !== realpath(DEFAULT_CONFIG))
        {
            $config = DEFAULT_CONFIG;
        }
        else
        {
            /* We're in dev so find the data file path */
            $config = realpath(dirname(__FILE__).'/../../data/ircbot.ini');
        }
        $this->config->loadIniFile($config);

        $index = 0;
        while (null !== ($connectionConf = $this->config->get('connection'.$index++))) {
            $connectionConf         = array_merge($this->config->get('global'), $connectionConf);
            $socket                 = new ircSocket($connectionConf[ircSocket::SERVER], $connectionConf[ircSocket::PORT]);
            $this->connectionList[] = new ircConnection($connectionConf, $socket, $this);
        }
        $this->registerPlugins();
        $this->runConnections();
    }

    private function registerPlugins()
    {
        $path = realpath(dirname(__FILE__).DIRECTORY_SEPARATOR.$this->config->get('global', 'pluginpath'));
        if (false === $path)
        {
            print('Plugins path not found '.dirname(__FILE__).DIRECTORY_SEPARATOR.$this->config->get('global', 'pluginpath')."\n");
            return;
        }
    	$handle = opendir($path);
    	while (false !== ($plugin = readdir($handle)))
    	{
    		if (true === is_file($path.DIRECTORY_SEPARATOR.$plugin) && substr($plugin,-4) === '.php')
    		{
				include_once($path.DIRECTORY_SEPARATOR.$plugin);
				$pluginName  = substr($plugin, 0, -4);
		        $pluginClass = '\\'.$pluginName; //to get around namespaceing issues.
                foreach ($this->connectionList as $connection)
                {
                    $pluginConfig = $this->config->get($pluginName);
		    	    if ('1' === $pluginConfig['enabled'])
                    {
                        $plugin = new $pluginClass($connection, $pluginConfig);
                        $connection->registerPlugin($plugin);
                    }
                    $plugin = null;
                }
    		}
    	}
    	closedir($handle);

    }


    /**
     * Run the irc connections, fork if necessary
     *
     * @return void
     */
    private function runConnections()
    {
        if (true === empty($this->connectionList))
        {
            //nothing to do, but we could fire up a command console in the future
        }
        elseif (1 === count($this->connectionList))
        {
            $connection = array_pop($this->connectionList);
            $connection->run();
        }
        else
        {
            $childPid = array();
            foreach($this->connectionList as $connection)
            {
                $pid = pcntl_fork();
                if ($pid == -1)
                {
                    die("Stopping, could not Fork\n");
                }
                elseif(!$pid)
                {
                    /* I am a child, kill child when connection dies */
                    $connection->run();
                    exit(0);
                }
                else
                {
                    /* I am the parent, collect the PIDs */
                    $childPid[] = $pid;
                }
            }
            foreach ($childPid as $pid)
            {
                /* Wait for children to exit */
                pcntl_waitpid($pid, $status);
            }
        }
    }

    /**
     * Reset the singleton for testing purposes.
     *
     * @return void
     */
    public static function reset()
    {
        self::$instance = null;
    }

    public function debugPrint($message, $debug=false)
    {
        if (true === $debug || $this->config->get('global', 'debug'))
        {
            echo print_r($message, true)."\n";
        }
    }
}