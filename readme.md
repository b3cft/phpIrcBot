phpIrcBot
=========

An irc bot implemented in php, roughly a clone of [irccat](http://github.com/RJ/irccat) with some extra features I liked from other bots thrown in.

For the love of {insert deity} why?
------------------------

1. I was bored
2. I kinda wanted to learn stuff
3. I wanted a reasonable project to try out jenkins features and plugins for php code analysis
4. I plan to write this project is several languages, php is an easy prototype (for me)
5. To annoy my cow-workers

*Note* I run my instance of `phpIrcBot` under the name `overlord`.
Anywhere you see `overlord` substitute the name of the ircbot you have configured.

Installation
------------

You can currently chose to install via Pear or download/checkout the code.

### Pear install

    pear install channel://pear.b3cft.com/IRCBot

### Download
Download or clone the project from [phpIrcBot Downloads](http://github.com/b3cft/phpIrcBot/downloads)
This code currently (Dec 2011) relies on two pear projects:

    pear install channel://pear.gradwell.com/autoloader
    pear install channel://pear.b3cft.com/CoreUtils

### Configure

From the downloaded/cloned version you can copy the example config in `src/data/ircbot.example.ini`

From the pear installed version you can copy the example config located in pear's `data_dir`, if you don't know where this is you can use:

    pear config-get data_dir

the pear packaged version will be inside an `IRCBot` directory.

Copy the `ircbot.example.ini` to `ircbot.ini` in the same location and open in the text editor of your choice.

### Running
    
From a downloaded/cloned source `src/bin/ircbot.php` should suffice. 

Building/Extending
------------------

The build system I'm using is `ant` and there are several php based build tools used:

    pear config-set auto_discover 1
    pear install PHP_CodeSniffer
    pear install PhpDocumentor
    pear install channel://pear.phpmd.org/PHP_PMD
    pear install channel://pear.pdepend.org/PHP_Depend
    pear install channel://pear.phpunit.de/phpcpd
    pear install channel://pear.phpunit.de/phploc
    pear install channel://pear.phpunit.de/PHP_CodeBrowser
    pear install channel://pear.phpunit.de/PHPUnit

You may not want all of those, but `phpunit` I would heartily recommend and if you are going to commit back, please use the PHP\_CodeSniffer to validate to the coding standards.

Running `ant` will execute a complete unit test and coverage report and dump the output in the `build` directory.

If you want to see the project [coverage](http://git.b3cft.com:8080/job/phpIrcBot/Code_Coverage/), [docs](http://git.b3cft.com:8080/job/phpIrcBot/API_Documentation/) or [other metrics](http://git.b3cft.com:8080/job/phpIrcBot/Code_Browser/?) they *should* be available [here](http://git.b3cft.com:8080/job/phpIrcBot/lastSuccessfulBuild/) 

Basic Operation
---------------
`overlord` has some basic commands that are hopefully self explanatory.

For most actions you can direct message `overlord` or direct a message to him in a channel.

e.g.

    /msg overlord help
or in channel
    overlord: help
for both he will return a link to this page.

Some commands he will only direct message back, e.g. asking for a list of commands:

    overlord: commands
    /msg overlord commands

should result in a private message consisting something like

    ?ampm, ?args, ?hits
    addop, delop, deop, devoice, join
    kick, leave, op, part, part
    ping, showops, stats, uptime, version
    voice, welcome, welcomeTopic

Built in commands
-----------------

Commands that are not prefixed with a ? are built in commands to `overlord` or provided by a plugin.
### channel commands

    overlord: join forge
    overlord: leave forge
    /msg overlord join forge
    /msg overlord leave forge

`part` can also be used and is a synonym for leave.

Channels can be referenced with or without the #prefix.

### Mode commands
Should overlord be in a channel in which he is an operator, you can ask overlord to op, deop, voice and devoice you.
in a channel

    overlord: op
    overlord: deop
    overlord: voice
    overlord: devoice

overlord will grant or remove op and voice privileges. However, be warned, he is fickle.

    overlord: op b3cft
    overlord: deop b3cft
    etc...

overlord will grant or remove ops and voice from another user (b3cft)

Extensible Commands
-------------------

Commands prefixed with a ? are actually scripts located on the filesystem.
*N.B.* you do not need to direct the message to overlord for these commands, but he will understand if you do.

Overlord will execute them passing in parameters based on the requests, roughly as such:

    {nick} {channel} {sender} {command} {args...}

so in \#frameworks and asking overlord `?mycommand one two three` will result in 

    mycommand overlord \#frameworks b3cft mycommand one two three 

being executed on the server.

Send the same message to overlord in a direct message will result in

    mycommand overlord null b3cft mycommand one two three 

Command can be written in any language as long they are executable in a shell. Any output (currently) is echoed back to the channel or user that orginated the request.

###Example commands
 * `?eagles` reports who the current eagles are (also the same command updates!)
 * `?ampm` responds with morning or afternoon
 * `?dod` reports the confluence location of Frameworks Definition of Done
 * `?hits` looks the term up on google and reports the number of hits

I may take recommendations for new features.

Plugins
-------

There are several plugins to extend overlord I have currently written.

 * `oper` handles the oping and deop in channels and who is allowed to execute.
 * `subber` will rewrite typos in channels based on people posting `s/search/replace/` or `^search^replace` replacements. (this is quite good fun!)
 * `welcomer` spots newcomers to a channel and send them the topic message (customisable with the `welcometopic` command) also `overlord welcome bob` will welcome bob to the channel.

