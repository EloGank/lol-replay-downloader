<?php

namespace Example;

use Buzz\Browser;
use EloGank\Replay\Client\ReplayClient;
use EloGank\Replay\Downloader\ReplayDownloader;
use Example\Utils\BasicOutput;

require __DIR__ . '/../../../../vendor/autoload.php';
require __DIR__ . '/Utils/BasicOutput.php';

$output = new BasicOutput();

$output->write('Parsing LoLNexus content to retrieve encryption key & game id...');

$buzz = new Browser();
$response = $buzz->get('http://www.lolnexus.com/recent-games?filter-region=2&filter-sort=1');
$output->write('.');

if (!preg_match('/<a class="green-button scouter-button" href="\/EUW\/search\?name=(.*)">Live Game<\/a>/', $response->getContent(), $matches)) {
    die('Cannot parse LoLNexus website #1');
}

$output->write('.');
$response = $buzz->get('http://www.lolnexus.com/ajax/get-game-info/EUW.json?name=' . $matches[1]);

if (!preg_match('/lrf:\/\/spectator [0-9.:]+ (.*) ([0-9]+) EUW/', $response->getContent(), $matches)) {
    die('Cannot parse LoLNexus website #2');
}

$output->writeln('.');

$replayDownloader = new ReplayDownloader(new ReplayClient(), __DIR__ . '/replays');
$replay = $replayDownloader->createReplay('EUW1', $matches[2], $matches[1]);
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