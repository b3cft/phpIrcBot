#!/usr/bin/env php
<?php
namespace b3cft\IrcBot;
use b3cft\CoreUtils\Config,
    b3cft\CoreUtils\Registry;
require_once 'gwc.autoloader.php';
$devPath = realpath(dirname(__FILE__).'/../');
if (false === empty($devPath))
{
    __gwc_autoload_alsoSearch($devPath);
}

/* Load the default config */
Config::getInstance()->loadIniFile('../data/ircbot.ini');

/* Register the config object in registry */
Registry::getInstance()->register('Config', Config::getInstance());

/* Start the IRC bot */
IrcBot::getInstance()->init();