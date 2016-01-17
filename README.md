# dannybot: your best friend, and your sexiest enemy

About me: I'm an [AB/DL](https://en.wikipedia.org/wiki/Paraphilic_infantilism) who hangs out in AB/DL IRC channels (among many others). I also code. If PHP counts, that is.

dannybot started out as something I wrote in high school. It was still in PHP, but it was horrendous code and it usually crashed once the call stack got 1024 entries deep. Yeeeeeeeahhh...

This is a rewrite of dannybot in modern OO PHP. Currently, it runs on PHP 5.5 and up, but may soon require 5.6 or even 7. It depends on which language features I need.

## Getting started

Copy `config.php.example` to `config.php`, edit it, and start 'er up with `php dannybot.php`. Plugins are found under `src/IRC/Plugin`.

## Writing plugins

It doesn't really matter which PHP namespace you place your plugins under, as long as they extend `DannyTheNinja\IRC\Plugin\AbstractPlugin`.

You must declare a method called `loadPlugin`. This method will make any necessary calls to `$this->bind()` to attach to IRC events. You can attach a callback to one or several IRC events, and several common opcodes are declared in the `DannyTheNinja\IRC\Opcode` class.

The callback passed to `bind` will be passed two parameters: `$irc` and `$msg`. `$irc` is the instance of `DannyTheNinja\IRC\Client`. `$msg` is an array containing information about the message received. Experiment with it to figure out what you need from it. Eventually it will likely be replaced with an object which will more strictly define the attributes you receive.