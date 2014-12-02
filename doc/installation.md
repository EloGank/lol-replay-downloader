League of Legends Replay Downloader
================================

## Composer installation

Composer is a dependency manager for PHP.

### How to install

Simply go on the root project directory and run these commands :

``` bash
curl -sS https://getcomposer.org/installer | php 
sudo mv composer.phar /usr/local/bin/composer
```

Then, run this command to install dependencies :

``` bash
composer install
```

### Install as dependency

If you want install this library as dependency, simply add to your `composer.json` file :

``` js
"require": {
    // ...
    "elogank/lol-replay-downloader": "~1.0.0"
}
```

Then, execute the `composer install` command.