<?php

/*
 * This file is part of the "EloGank League of Legends Replay Downloader" package.
 *
 * https://github.com/EloGank/lol-replay-downloader
 *
 * For the full license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace Example;

use EloGank\Replay\Client\ReplayClient;
use EloGank\Replay\Downloader\ReplayDownloader;
use Example\Utils\BasicOutput;
use Example\Utils\LoLNexusParser;

require __DIR__ . '/../vendor/autoload.php';
// require __DIR__ . '/../../../../vendor/autoload.php';
require __DIR__ . '/utils/BasicOutput.php';
require __DIR__ . '/utils/LoLNexusParser.php';

$output = new BasicOutput();

// Parsing process
$output->writeln('Parsing LoLNexus content to retrieve a random game data...');
$parser = new LoLNexusParser();

try {
    $parser->parse(LoLNexusParser::REGION_EUW, $output);
} catch (\RuntimeException $e) {
    $output->writeln([
        '',
        'ERROR: ' . $e->getMessage()
    ]);

    die;
}

// Downloading process
$output->writeln(['', 'Replay downlading process :']);
$replayDownloader = new ReplayDownloader(new ReplayClient(), __DIR__ . '/replays');
$replay = $replayDownloader->createReplay($parser->getRegionUniqueName(), $parser->getGameId(), $parser->getEncryptionKey());
$replayDownloader->createDirs($replay->getRegion(), $replay->getGameId());

$output->writeln('Downloading metas');
$replayDownloader->downloadMetas($replay);

$output->writeln('Validate criteria');
$replayDownloader->isValid($replay, $output);

$output->writeln('Download previous chunks');
$lastChunkInfo = $replayDownloader->getLastChunkInfos($replay);
$replay->setLastChunkId($lastChunkInfo['chunkId']);
$replay->setLastKeyframeId($lastChunkInfo['keyFrameId']);
$replayDownloader->downloadChunks($replay);

$output->writeln('Downloading previous keyframes');
$replayDownloader->downloadKeyframes($replay, $output);

$output->writeln('Downloading current data');
$replayDownloader->downloadCurrentData($replay, $output);

$output->writeln('Update metas');
$replayDownloader->updateMetas($replay);

$output->writeln([
    '',
    'Finished'
]);