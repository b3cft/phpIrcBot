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

use b3cft\IrcBot\IrcBot,
    b3cft\CoreUtils\Registry;

/* Include PSR0 Autoloader and add dev path to search */
if (false === defined('PSR0AUTOLOADER'))
{
    include_once 'gwc.autoloader.php';
    $devPath = realpath(dirname(__FILE__).'/../');
    if (false === empty($devPath))
    {
        __gwc_autoload_alsoSearch($devPath);
    }
    define('PSR0AUTOLOADER', true);
}


/**
 *
 *
 * @author b3cft
 * @covers b3cft\IrcBot\IrcBot
 *
 */
class IrcBotTest extends PHPUnit_Framework_TestCase
{

    /**
     * Test singleton method
     *
     * @covers b3cft\IrcBot\IrcBot::getInstance
     *
     * @return void
     */
    public function testSingleton()
    {
        $ircBot = IrcBot::getInstance();

        $this->assertInstanceOf(
            'b3cft\IrcBot\IrcBot',
            $ircBot,
            'Didn\'t get an instance of IrcBot from getInstance()'
        );
    }

    /**
     * Test Init
     *
     * @covers b3cft\IrcBot\IrcBot::init
     *
     * @return return_type
     */
    public function testInit()
    {
        $mockConfig = $this->getMock(
            'b3cft\CoreUtils\Config', /* Name of class         */
            array(),                  /* Methods to mock       */
            array(),                  /* Constructor arguments */
            '',                       /* Name of mocked class  */
            false                     /* Call the constructor  */
        );



        Registry::getInstance()->register('Config', $mockConfig);

        IrcBot::getInstance()->init();

        $this->assertAttributeEquals(
            $mockConfig,
            'config',
            IrcBot::getInstance(),
            'MockConfig not compositied onto IrcBot'
        );
    }

    /**
     * Test that default config constant is used if defined
     *
     * @return void
     */
    public function testInitWithConstant()
    {
        $mockConfig = $this->getMock(
            'b3cft\CoreUtils\Config', /* Name of class         */
            array(),                  /* Methods to mock       */
            array(),                  /* Constructor arguments */
            '',                       /* Name of mocked class  */
            false                     /* Call the constructor  */
        );

        define('DEFAULT_CONFIG', dirname(__FILE__).'/fixtures/dummyConfig.ini');

        Registry::getInstance()->register('Config', $mockConfig);

        IrcBot::getInstance()->init();

        $this->assertAttributeEquals(
            $mockConfig,
            'config',
            IrcBot::getInstance(),
            'MockConfig not compositied onto IrcBot'
        );
    }

    /**
     * Test init with a config file parameter uses the passed config file
     *
     * @return void
     */
    public function testInitWithParameter()
    {
        $mockConfig = $this->getMock(
            'b3cft\CoreUtils\Config', /* Name of class         */
            array(),                  /* Methods to mock       */
            array(),                  /* Constructor arguments */
            '',                       /* Name of mocked class  */
            false                     /* Call the constructor  */
        );

        $params = array(IrcBot::PARAM_CONFIG_FILE => dirname(__FILE__).'/fixtures/dummyConfig.ini');

        Registry::getInstance()->register('Config', $mockConfig);

        IrcBot::getInstance()->init($params);

        $this->assertAttributeEquals(
            $mockConfig,
            'config',
            IrcBot::getInstance(),
            'MockConfig not compositied onto IrcBot'
        );
    }

    /**
     * Test reseting singleton for testing purposes
     *
     * @return void
     */
    public function testReset()
    {
        $mockConfig = $this->getMock(
            'b3cft\CoreUtils\Config', /* Name of class         */
            array(),                  /* Methods to mock       */
            array(),                  /* Constructor arguments */
            '',                       /* Name of mocked class  */
            false                     /* Call the constructor  */
        );

        Registry::getInstance()->register('Config', $mockConfig);
        IrcBot::getInstance()->init();
        $this->assertAttributeEquals(
            $mockConfig,
            'config',
            IrcBot::getInstance(),
            'MockConfig not compositied onto IrcBot'
        );
        IrcBot::reset();
        $this->assertAttributeEmpty(
            'config',
            IrcBot::getInstance(),
            'Singleton not reset correctly'
        );
    }
}