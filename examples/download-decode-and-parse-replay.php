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
 * This example allows you to call a method each time the ReplayDownloader decode a replay file (chunk or keyframe).
 * In this particular example, it allows you to know how many turret has been destroyed by both teams.
 */

namespace Example;

use EloGank\Replay\Downloader\Client\ReplayClient;
use EloGank\Replay\Downloader\ReplayDownloader;
use EloGank\Replay\ReplayInterface;
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

// ####################################################################################
// Here, we create custom ReplayDownloader class that extends original ReplayDownloader
// to override onReplayFileDecrypted() method
// ####################################################################################
class MyReplayDownloader extends ReplayDownloader
{
    /**
     * This method counts how many turrets were destroyed by a team
     *
     * @param ReplayInterface $replay
     * @param string          $fileType
     * @param int             $fileId
     * @param string          $binary
     *
     * @return bool
     */
    protected function onReplayFileDecrypted(ReplayInterface $replay, $fileType, $fileId, $binary)
    {
        if (ReplayDownloader::FILETYPE_KEYFRAME == $fileType) {
            echo PHP_EOL; // formatting, don't care

            // Work with hex, easier
            $hex = strtoupper(bin2hex($binary));
            // Search for "Turret_T1" or "Turret_T2" strings
            if (!preg_match_all('/5475727265745F54[31|32]/', $hex, $matches, PREG_OFFSET_CAPTURE)) {
                echo 'The keyframe #' . $fileId . ' has no turret' . PHP_EOL;
            }

            $turrets = [];
            foreach ($matches[0] as $turretId => $match) {
                $turretHex = substr($hex, $match[1]);

                // Byte after first 0x00 are meaningless
                $pos = strpos($turretHex, '00');
                if (1 === $pos % 2) {
                    ++$pos;
                }

                // Convert hex to readable string
                $turretName = hex2bin(substr($turretHex, 0, $pos));
                if (!is_string($turretName)) {
                    echo 'The parsing turret method seems to be outdated, please report an issue' . PHP_EOL;
                }

                // Wrong ?
                if (false === strpos($turretName, 'Turret_T')) {
                    continue;
                }

                $team = 'blue';
                if (false !== strpos($turretName, 'T2')) {
                    $team = 'purple';
                }

                $turrets[$team][] = $turretName;
            }

            // Count the destroyed turrets
            echo ' - Minute ' . ($fileId - 1) . ' :' . PHP_EOL;
            foreach ($turrets as $teamName => $turretNames) {
                echo '   > The ' . $teamName . ' team has destroyed ';

                $opponent = 'blue';
                if ('blue' == $teamName) {
                    $opponent = 'purple';
                }

                // There are 11 turrets by team
                echo (11 - count($turrets[$opponent])) . ' turret(s)' . PHP_EOL;
            }

            echo "\t\t\t\t"; // formatting, don't care
        }
    }
}

$replayDownloader = new MyReplayDownloader(new ReplayClient(), __DIR__ . '/replays', [
    // #####################################################################################
    // Here, we tell to ReplayDownloader to decode but NOT save decoded files
    // (save is useless and take disk space) into chunks.decoded & keyframes.decoded folders
    // #####################################################################################
    'replay.decoder.enable'     => true,
    'replay.decoder.save_files' => false
]);
$replayDownloader->download($parser->getRegion(), $parser->getGameId(), $parser->getEncryptionKey(), $output, true);

$output->writeln([
    '',
    'Finished'
]);
