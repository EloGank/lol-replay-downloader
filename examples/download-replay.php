<?php

/*
 * This file is part of the "EloGank League of Legends Replay Downloader" package.
 *
 * https://github.com/EloGank/lol-replay-downloader
 *
 * For the full license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/*
 * This example shows you how to use the Replay Downloader library.
 */

namespace Example;

use EloGank\Replay\Downloader\Client\ReplayClient;
use EloGank\Replay\Downloader\ReplayDownloader;
use Example\Utils\BasicOutput;
use Example\Utils\LoLNexusParser;

require __DIR__ . '/../vendor/autoload.php';
// require __DIR__ . '/../../../../vendor/autoload.php'; // if running example from the CLI project
require __DIR__ . '/utils/BasicOutput.php';
require __DIR__ . '/utils/LoLNexusParser.php';

$output = new BasicOutput();

// Parsing process
$output->writeln('Parsing LoLNexus content to retrieve a random game data :');
$parser = new LoLNexusParser();

try {
    $parser->parseRandom(LoLNexusParser::REGION_EUW, $output);
} catch (\RuntimeException $e) {
    $output->writeln([
        '',
        'ERROR: ' . $e->getMessage()
    ]);

    die;
}

// Downloading process
$output->writeln(['', 'Replay downlading process for #' . $parser->getGameId() . ' (' . $parser->getRegion() . ') :']);
$replayDownloader = new ReplayDownloader(new ReplayClient(), __DIR__ . '/replays');
$replayDownloader->download($parser->getRegion(), $parser->getGameId(), $parser->getEncryptionKey(), $output, true);

$output->writeln([
    '',
    'Finished'
]);
