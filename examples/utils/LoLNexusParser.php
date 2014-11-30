<?php

/*
 * This file is part of the "EloGank League of Legends Replay Downloader" package.
 *
 * https://github.com/EloGank/lol-replay-downloader
 *
 * For the full license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Example\Utils;

use Buzz\Browser;
use EloGank\Replay\Output\OutputInterface;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@gmail.com>
 */
class LoLNexusParser
{
    const REGION_NA   = 1;
    const REGION_EUW  = 2;
    const REGION_EUNE = 3;
    const REGION_BR   = 4;
    const REGION_TR   = 5;
    const REGION_RU   = 6;
    const REGION_LAN  = 7;
    const REGION_LAS  = 8;
    const REGION_OCE  = 9;

    /**
     * @var string
     */
    protected $region;

    /**
     * @var int
     */
    protected $gameId;

    /**
     * @var string
     */
    protected $encryptionKey;

    protected static $serverNames = [
        'NA', 'EUW', 'EUNE', 'BR', 'TR', 'RU', 'LAN', 'LAS', 'OCE'
    ];


    /**
     * Select the first player in the recent games list for a selected region
     *
     * @param int             $regionId
     * @param OutputInterface $output
     */
    public function parseRandom($regionId = self::REGION_EUW, OutputInterface $output = null)
    {
        $hasOutput = null != $output;
        if ($hasOutput) {
            $output->write("Parsing homepage...\t\t\t");
        }

        $buzz = new Browser();
        $response = $buzz->get('http://www.lolnexus.com/recent-games?filter-region=' . $regionId . '&filter-sort=1');

        if (!preg_match('/<a class="green-button scouter-button" href="\/' . self::$serverNames[$regionId - 1] . '\/search\?name=(.*)">Live Game<\/a>/', $response->getContent(), $matches)) {
            throw new \RuntimeException('Cannot parse LoL Nexus website, maybe down ?');
        }

        if ($hasOutput) {
            $output->writeln('<info>OK</info>');
        }

        $this->parsePlayer($regionId, $matches[1], $output, $buzz);
    }

    /**
     * @param int                  $regionId
     * @param string               $playerName
     * @param null|OutputInterface $output
     * @param null|Browser         $buzz
     */
    public function parsePlayer($regionId, $playerName, OutputInterface $output = null, $buzz = null)
    {
        if (null == $buzz) {
            $buzz = new Browser();
        }

        $hasOutput = null != $output;
        if ($hasOutput) {
            $output->write("Parsing game page...\t\t\t");
        }

        $response = $buzz->get('http://www.lolnexus.com/ajax/get-game-info/' . self::$serverNames[$regionId - 1] . '.json?name=' . urlencode($playerName));

        if (!preg_match('/lrf:\/\/spectator [0-9.:]+ (.*) ([0-9]+) ([A-Z0-9]+) [0-9\.]+/', $response->getContent(), $matches)) {
            throw new \RuntimeException('Cannot parse LoL Nexus game page, the game may be ended (sometimes LoLNexus\'s cache is bad), please retry.');
        }

        if ($hasOutput) {
            $output->writeln('<info>OK</info>');
        }

        $this->encryptionKey    = $matches[1];
        $this->gameId           = $matches[2];
        $this->region           = $matches[3];
    }

    /**
     * @return string
     */
    public function getEncryptionKey()
    {
        return $this->encryptionKey;
    }

    /**
     * @return int
     */
    public function getGameId()
    {
        return $this->gameId;
    }

    /**
     * @return string
     */
    public function getRegion()
    {
        return $this->region;
    }
}
