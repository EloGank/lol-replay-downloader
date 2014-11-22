<?php

/*
 * This file is part of the "EloGank League of Legends Replay Downloader" package.
 *
 * https://github.com/EloGank/lol-replay-downloader
 *
 * For the full license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace EloGank\Replay;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@gmail.com>
 */
interface ReplayInterface
{
    /**
     * @return array
     */
    public function getMetas();

    /**
     * @param array $metas
     */
    public function setMetas(array $metas);

    /**
     * @return string
     */
    public function getEncryptionKey();

    /**
     * @param string $encryptionKey
     */
    public function setEncryptionKey($encryptionKey);

    /**
     * @return int
     */
    public function getGameId();

    /**
     * @param int $gameId
     */
    public function setGameId($gameId);

    /**
     * @return string
     */
    public function getRegion();

    /**
     * @param string $region
     */
    public function setRegion($region);

    /**
     * @param int $duration
     */
    public function setDuration($duration);

    /**
     * @return int
     */
    public function getLastChunkId();

    /**
     * @param int $lastChunkId
     */
    public function setLastChunkId($lastChunkId);

    /**
     * @return int
     */
    public function getLastKeyFrameId();

    /**
     * @param int $lastKeyFrameId
     */
    public function setLastKeyFrameId($lastKeyFrameId);

    /**
     * @return int
     */
    public function getDownloadRetry();

    /**
     */
    public function addDownloadRetry();

    /**
     */
    public function resetDownloadRetry();
} 