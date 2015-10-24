League of Legends Replay Downloader
================================

This project provides you a way to easily download a League of Legends replay game which still in process (ingame), like *lolking.net* or *op.gg* feature. Replays are stored in your server and can be watched at any time.  
All download files can be decoded to parse them.

## Features

* **A built-in replay decoder for each files (chunks & keyframes)**.
* **An asynchronous system *(multi processes)*, allow to download some replays at the same time and save the log into the replay folder**, it needs the CLI dependency : https://github.com/EloGank/lol-replay-downloader-cli.
* Download previous data if you start the download process after the start of the game (can download only 10 minutes in the past due to a limitation of the Riot spectator server engine).
* Can wait for the start of the game if you start the download process too early (during the game loading process or the first 3 minutes of the game).
* **Easily extendable and configurable**.


## Installation

### Composer

Simply clone this project and run the `composer install` command.
If you don't know what is Composer, read the [dedicated documentation](./doc/installation.md).

## Configuration

To configure the library, you have some `$options` parameters in the classes constructor. Just pass an array to override them, see the `getDefaultOptions()` method of each class.

See the "[download and decode example](./examples/download-and-decode-replay.php)", which overrides two configurations of the `ReplayDownloader` class.

### Notes with xDebug

If you have enabled xDebug, please set your max nesting level to more than 200. This lib uses a recursive method to download a game data, and can reach the max value (100) when a game length is more than 40 minutes.  
To edit the default max nesting level, open your xDebug configuration file (`/etc/php5/your_engine(cli, fpm or apache2)/conf.d/20-xdebug.ini` by default) and append this :

``` ini
[xdebug]
xdebug.max_nesting_level = 300
```

## How to use (examples)

Some examples are available in the [examples repository folder](./examples).

## How to get the region, game id or encryption key ?

Some regions (not all) are listed in the [LoLNexusParser](./examples/utils/LoLNexusParser.php) class as constants.

### From an unofficial API

For the **game id** and the **encryption key**, it's a few harder. Indeed, the [official Riot API](https://developer.riotgames.com/) doesn't provide yet an API to retrieve this data.  
To get it, you have to use an unofficial API, like this : https://github.com/EloGank/lol-php-api, please see the route `game.retrieve_in_progress_spectator_game_info`. Note that using other route is not allowed, by the new Riot Terms of Use (see "Important notes" below).  

### From LoLNexus website

For testing purpose, you can simply go to spectating websites like [lolnexus](http://www.lolnexus.com), click on "Spectate" button on a game, and you'll have the region, game id & encryption key in the command line to launch the game, see the end of the line :

``` bash
"C:\Riot Games\League of Legends\RADS\solutions\lol_game_client_sln\releases\0.0.1.xx\deploy\League of Legends.exe" "8394" "LoLLauncher.exe" "" "spectator SERVER_ADDRESS ENCRYPTION_KEY GAME_ID REGION"
```

Example :

``` bash
"C:\Riot Games\League of Legends\RADS\solutions\lol_game_client_sln\releases\0.0.1.68\deploy\League of Legends.exe" "8394" "LoLLauncher.exe" "" "spectator 185.40.64.163:80 nwP+BEYqHgk4sElnU2uRogoxGPUw1dzE 1234567890 EUW1"
```

So, you can extract :

Region | Game ID | Encryption Key
------------ | ------------- | -------------
EUW1 | 1234567890 | nwP+BEYqHgk4sElnU2uRogoxGPUw1dzE

### From LoLNexus parser

A LoLNexus PHP parser exists here : https://github.com/EloGank/lol-replay-downloader/blob/master/examples/utils/LoLNexusParser.php

Usage is simple : you juste have to select the region by calling `LoLNexusParser::parseRandom($regionId)` or `LoLNexusParser::parsePlayer($regionId, $playerName)` methods and it will bring you all parameters for running a command by calling `LoLNexusParser::getRegion()`, `LoLNexusParser::getGameId()` or `LoLNexusParser::getEncryptionKey()` methods.

Example is available here : https://github.com/EloGank/lol-replay-downloader/blob/master/examples/download-replay.php#L32-L48

## Important notes

According to the new Riot Terms of Use *(1st October 2014)*, using data from another source of their official API is **not** allowed. So using data by parsing decoded files is not allowed. This project provides a way to decode file only for teaching purpose.

**You can download a full game only if you start the download process before the ~8th ingame minute.** Otherwise, you won't have the start of the game.

A game is still viewable if the game hasn't been updated. If one or more updates has been applied since the replay has been downloaded, there may be has some ingame glitches (movements, monsters, sounds, etc).

## Reporting an issue or a feature request

Feel free to open an issue, fork this project or suggest an awesome new feature in the [issue tracker](https://github.com/EloGank/lol-replay-downloader/issues).  

## Credit

See the list of [contributors](https://github.com/EloGank/lol-replay-downloader/graphs/contributors).

## Licence

[MIT, more information](./LICENSE)

*This repository isn't endorsed by Riot Games and doesn't reflect the views or opinions of Riot Games or anyone officially involved in producing or managing League of Legends.  
League of Legends and Riot Games are trademarks or registered trademarks of Riot Games, Inc. League of Legends (c) Riot Games, Inc.*
