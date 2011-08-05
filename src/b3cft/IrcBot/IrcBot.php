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
 * @link       http://b3cft.github.com/phpIrcBot
 * @version    @@PACKAGE_VERSION@@
 */

namespace b3cft\IrcBot;
use b3cft\CoreUtils\Registry;

/**
 * Utility class for storing and dealing with configuration data
 *
 * Stores config data in groups
 *
 * @category   PHP
 * @package    b3cft
 * @subpackage Core
 * @author     Andy 'Bob' Brockhurst, <andy.brockhurst@b3cft.com>
 * @license  http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @link     http://github.com/b3cft/phpIRCBot
 */
class IrcBot
{

    private static $instance;
    public static $testing = false;

    /**
     * Container for config utility
     *
     * @var b3cft\CoreUtils\Config
     */
    private $config;

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
     * @return void
     */
    public function init()
    {
        $this->config = Registry::getInstance()->retrieve('Config');

        if (true === defined('DEFAULT_CONFIG') && true === realpath(DEFAULT_CONFIG))
        {
            $config = DEFAULT_CONFIG;
        }
        else
        {
            /* We're in dev so find the data file path */
            $config = realpath(dirname(__FILE__).'/../../data/ircbot.ini');
        }
        $this->config->loadIniFile($config);
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
}