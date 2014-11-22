<?php

/*
 * This file is part of the "EloGank League of Legends Replay Downloader" package.
 *
 * https://github.com/EloGank/lol-replay-downloader
 *
 * For the full license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace EloGank\Replay\Downloader;

use EloGank\Replay\Client\ReplayClient;
use EloGank\Replay\Crypt\ReplayCrypt;
use EloGank\Replay\Downloader\Exception\GameEndedException;
use EloGank\Replay\Downloader\Exception\GameNotFoundException;
use EloGank\Replay\Downloader\Exception\GameNotStartedException;
use EloGank\Replay\Downloader\Exception\ReplayFolderAlreadyExistsException;
use EloGank\Replay\Replay;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@gmail.com>
 *
 * TODO delete OutputInterface dependancy
 */
class ReplayDownloader
{
    const FILETYPE_KEYFRAME = 'KEYFRAME';
    const FILETYPE_CHUNK    = 'CHUNK';

    /**
     * @var string
     */
    protected $path;

    /**
     * @var ReplayClient
     */
    protected $client;

    /**
     * @var array
     */
    protected $options;


    /**
     * @param ReplayClient $client
     * @param string       $path
     * @param array        $options
     */
    public function __construct(ReplayClient $client, $path, array $options = [])
    {
        $this->client = $client;
        $this->path = $path;
        $this->options = array_merge($this->getDefaultOptions(), $options);
    }

    /**
     * @param string $region
     * @param int    $gameId
     * @param bool   $isAsync
     * @param string $encryptionKey
     *
     * @return Replay
     *
     * @throws GameNotFoundException
     */
    public function download($region, $gameId, $isAsync = false, $encryptionKey)
    {
        // Check if exists
        if (!$this->client->isGameExists($region, $gameId)) {
            throw new GameNotFoundException('The game #' . $gameId . ' is not found');
        }

        // Create directories
        $this->createDirs($region, $gameId);

        // Async
        if ($isAsync) {
            pclose(popen(sprintf('%s ' . $this->path . 'console replay:download %s %d %s --override > %s/info.log 2>&1 & echo $!', $this->options['php.executable_path'], $region, $gameId, $encryptionKey, $this->getReplayDirPath($region, $gameId)), 'r'));
        }

        return $this->createReplay($region, $gameId, $encryptionKey);
    }

    /**
     * @param string $region
     * @param int    $gameId
     * @param string $encryptionKey
     *
     * @return Replay
     */
    public function createReplay($region, $gameId, $encryptionKey)
    {
        // TODO make Replay class config

        return new Replay($region, $gameId, $encryptionKey);
    }

    /**
     * @param string $region
     * @param int    $gameId
     *
     * @throws ReplayFolderAlreadyExistsException
     */
    public function createDirs($region, $gameId)
    {
        // Check if exists
        $replayDirPath = $this->getReplayDirPath($region, $gameId);
        if (is_dir($replayDirPath)) {
            throw new ReplayFolderAlreadyExistsException('The replay #' . $gameId . ' already exists');
        }

        // Create dirs for replay
        $permissions = 0755;
        mkdir($replayDirPath . '/chunks', $permissions, true);
        mkdir($replayDirPath . '/keyframes', $permissions, true);

        // Save decrypted file only in dev
        if ($this->options['replay.decoder.enable'] && $this->options['replay.decoder.save_files']) {
            mkdir($replayDirPath . '/keyframes.decoded', $permissions, true);
            mkdir($replayDirPath . '/chunks.decoded', $permissions, true);
        }
    }

    /**
     * @param Replay $replay
     * @param int    $tries
     *
     * @return bool
     *
     * @throws GameEndedException
     * @throws GameNotStartedException
     */
    public function isValid(Replay $replay, $tries = 0)
    {
        if (null == $replay->getMetas()) {
            throw new \RuntimeException('You must call downloadMetas() method first');
        }

        // Download the new available metas to validate the game, only if retry
        if (0 < $tries) {
            $metas = $this->downloadMetas($replay);
        }
        else {
            $metas = $replay->getMetas();
        }

        // Retries
        if (!isset($metas['pendingAvailableKeyFrameInfo'][0])) {
            if ($tries < 20) {
                sleep(30);
                ++$tries;

                return $this->isValid($replay, $tries);
            }

            throw new GameNotStartedException('The game is not yet available for spectator, please wait tree minutes');
        }

        if ($metas['gameEnded']) {
            throw new GameEndedException('The game has already ended, cannot download it');
        }

        return true;
    }

    /**
     * @param Replay $replay
     *
     * @return mixed
     */
    public function downloadMetas(Replay $replay)
    {
        $gameId = $replay->getGameId();
        $metas = $this->client->getMetas($replay->getRegion(), $gameId);

        // Update replay object
        $metas = json_decode($metas, true);
        $metas['encryptionKey'] = $replay->getEncryptionKey();
        $replay->setMetas($metas);

        return $metas;
    }

    /**
     * @param Replay $replay
     */
    public function saveMetas(Replay $replay)
    {
        // Save the file
        file_put_contents($this->getReplayDirPath($replay->getRegion(), $replay->getGameId()) . '/metas.json', json_encode($replay->getMetas()));
    }

    /**
     * @param Replay $replay
     * @param int    $chunkId
     * @param int    $tries
     *
     * @return mixed
     *
     * @throws GameNotStartedException
     */
    public function getLastChunkInfos(Replay $replay, $chunkId = 30000, $tries = 0)
    {
        $lastInfos = json_decode($this->client->getLastChunkInfo($replay->getRegion(), $replay->getGameId(), $chunkId), true);
        if (0 === $lastInfos['chunkId']) {
            if ($tries > 10) {
                throw new GameNotStartedException('The game is not started');
            }

            sleep(30);

            return $this->getLastChunkInfos($replay, $chunkId, $tries + 1);
        }

        return $lastInfos;
    }


    /**
     * @param Replay $replay
     */
    public function downloadChunks(Replay $replay)
    {
        // Clear last chunks info
        $metas = $replay->getMetas();
        $metas['pendingAvailableChunkInfo'] = array();
        $replay->setMetas($metas);

        for ($i=1; $i<=$replay->getLastChunkId(); $i++) {
            $this->downloadChunk($replay, $i);
        }
    }

    /**
     * @param Replay          $replay
     * @param OutputInterface $output
     */
    public function downloadKeyframes(Replay $replay, OutputInterface $output)
    {
        // Clear last keyframes info
        $metas = $replay->getMetas();
        $metas['pendingAvailableKeyFrameInfo'] = array();
        $replay->setMetas($metas);

        for ($i=1; $i<=$replay->getLastKeyframeId(); $i++) {
            $this->downloadKeyframe($replay, $i, $output);
        }
    }


    /**
     * @param Replay $replay
     * @param int    $chunkId
     *
     * @return bool
     */
    private function downloadChunk(Replay $replay, $chunkId)
    {
        $chunk = $this->client->downloadChunk($replay->getRegion(), $replay->getGameId(), $chunkId);
        if (false === $chunk) {
            try {
                if ($chunkId > $this->findFirstChunkId($replay->getMetas()) && isset($replay->getMetas()['pendingAvailableChunkInfo'][0])) {
                    $replay->addDownloadRetry();
                    // TODO make config
                    if (15 == $replay->getDownloadRetry()) {
                        return false;
                    }
                    else {
                        sleep(1);

                        return $this->downloadChunk($replay, $chunkId);
                    }
                }
            }
            catch (\RuntimeException $e) {
                // No first chunk id was created, so it's the first download : we do nothing
            }

            // Clear retries
            $replay->resetDownloadRetry();

            return false;
        }

        file_put_contents($this->getReplayDirPath($replay->getRegion(), $replay->getGameId()) . '/chunks/' . $chunkId, $chunk);

        // Update metas
        $metas = $replay->getMetas();
        $metas['pendingAvailableChunkInfo'][] = array(
            'duration'     => 30000,
            'id'           => $chunkId,
            'receivedTime' => date('M n, Y g:i:s A')
        );
        $replay->setMetas($metas);

        return true;
    }

    /**
     * @param Replay          $replay
     * @param int             $keyframeId
     * @param OutputInterface $output
     *
     * @return bool
     */
    private function downloadKeyframe(Replay $replay, $keyframeId, OutputInterface $output)
    {
        $keyframe = $this->client->downloadKeyframe($replay->getRegion(), $replay->getGameId(), $keyframeId);
        if (false === $keyframe) {
            try {
                if ($keyframeId > $this->findKeyframeByChunkId($replay->getMetas(), $this->findFirstChunkId($replay->getMetas())) && isset($replay->getMetas()['pendingAvailableKeyFrameInfo'][0])) {
                    $replay->addDownloadRetry();
                    // TODO make config
                    if (15 == $replay->getDownloadRetry()) {
                        return false;
                    }
                    else {
                        sleep(1);

                        return $this->downloadChunk($replay, $keyframeId);
                    }
                }
            }
            catch (\RuntimeException $e) {
                // No first chunk id was created, so it's the first download : we do nothing
            }

            // Clear retries
            $replay->resetDownloadRetry();

            return false;
        }

        $pathFolder = $this->getReplayDirPath($replay->getRegion(), $replay->getGameId()) . '/keyframes';
        file_put_contents($pathFolder . '/' . $keyframeId, $keyframe);

        // Update metas
        $metas = $replay->getMetas();
        $metas['pendingAvailableKeyFrameInfo'][] = array(
            'id'           => $keyframeId,
            'receivedTime' => date('M n, Y g:i:s A'),
            'nextChunkId'  => ($keyframeId - 1) * 2 + $metas['startGameChunkId'],
        );
        $replay->setMetas($metas);

        if ($this->options['replay.decoder.enable']) {
            $crypt = new ReplayCrypt($replay);

            if (!$this->onReplayFileDecrypted($replay, self::FILETYPE_KEYFRAME, $keyframeId,
                $crypt->getBinary($replay, $pathFolder, $keyframeId, false)  // TODO make "false" as option
            )) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param Replay $replay   The replay
     * @param string $fileType Will be self::FILETYPE_KEYFRAME or self::FILETYPE_CHUNK
     * @param int    $fileId   The file id (which is the filename)
     * @param string $binary   The decrypted file binary
     *
     * @return bool True if success, false otherwise.
     */
    protected function onReplayFileDecrypted(Replay $replay, $fileType, $fileId, $binary)
    {
        // If you have to parse a decrypted file, do it here.

        return true;
    }

    /**
     * @param Replay          $replay
     * @param OutputInterface $output
     *
     * @return bool
     */
    public function downloadCurrentData(Replay $replay, OutputInterface $output)
    {
        $lastInfos = $this->getLastChunkInfos($replay, $replay->getLastChunkId());

        // End stats
        // TODO put in config this code, it download the last keyframe, but not needed now
        if ($lastInfos['endGameChunkId'] == $replay->getLastChunkId()) {
            $this->downloadKeyframe($replay, $replay->getLastKeyframeId(), true, $output);

            return true;
        }

        $downloadableChunkId = $replay->getLastChunkId() + 1;
        $output->write("Downloading chunk\t#" . $downloadableChunkId . "\t\t");
        $this->downloadChunk($replay, $downloadableChunkId);
        $replay->setLastChunkId($downloadableChunkId);
        $output->writeln('OK');

        if ($lastInfos['keyFrameId'] > $replay->getLastKeyframeId()) {
            $downloadableKeyframeId = $replay->getLastKeyframeId() + 1;
            $output->write("Downloading keyframe\t#" . $downloadableKeyframeId . "\t\t");
            $this->downloadKeyframe($replay, $downloadableKeyframeId, $output);
            $replay->setLastKeyframeId($downloadableKeyframeId);
            $output->writeln('OK');
        }

        // Downloading all chunks & keyframes might slow the current chunk
        if ($lastInfos['chunkId'] > $replay->getLastChunkId() || $lastInfos['keyFrameId'] > $replay->getLastKeyframeId()) {
            return $this->downloadCurrentData($replay, $output);
        }

        // Wait for the next available chunk
        usleep(($lastInfos['nextAvailableChunk'] + 500) * 1000); // micro, not milli

        gc_collect_cycles();

        // And again
        $this->downloadCurrentData($replay, $output);
    }

    /**
     * @param Replay $replay
     */
    public function updateMetas(Replay $replay)
    {
        // Download end game metas
        $endMetas = $this->client->getMetas($replay->getRegion(), $replay->getGameId());
        $endMetas = json_decode($endMetas, true);

        // Update metas
        $metas = $replay->getMetas();
        $metas['lastChunkId']       = $replay->getLastChunkId();
        $metas['endGameChunkId']    = $replay->getLastChunkId();
        $metas['lastKeyFrameId']    = $replay->getLastKeyframeId();
        $metas['endGameKeyFrameId'] = $replay->getLastKeyframeId();
        $metas['firstChunkId']      = $this->findFirstChunkId($metas);
        $metas['gameEnded']         = true;
        $metas['gameLength']        = $endMetas['gameLength'];

        // Update replay object
        $replay->setDuration(round($endMetas['gameLength'] / 1000));
        $replay->setMetas($metas);

        // Save metas
        $this->saveMetas($replay);
    }

    /**
     * @param array $metas
     *
     * @return int
     *
     * @throws \RuntimeException
     */
    public function findFirstChunkId(array $metas)
    {
        // Deleting startup chunks
        $finalChunks = array();
        $startGameChunkId = $metas['startGameChunkId'];
        foreach ($metas['pendingAvailableChunkInfo'] as $chunk) {
            if ($chunk['id'] >= $startGameChunkId) {
                $finalChunks[] = $chunk;
            }
        }

        $chunkId = null;
        $chunks  = array_reverse($finalChunks);

        // 55-54-53-51-50, will return 53 because 52 is missing
        // 55-54-53, will return 53
        foreach ($chunks as $i => $chunk) {
            if (isset($chunks[$i + 1]) && $chunks[$i + 1]['id'] == $chunk['id'] - 1 ||
                !isset($chunks[$i + 1]) && $chunkId - 1 == $chunk['id']) {
                $chunkId = $chunk['id'];
            }
            else {
                break;
            }
        }

        if (null == $chunkId || !isset($metas['pendingAvailableKeyFrameInfo'][0])) {
            throw new \RuntimeException('No first chunk id was found');
        }

        // If no keyframe available for selected chunk
        if ($chunkId < $metas['pendingAvailableKeyFrameInfo'][0]['nextChunkId']) {
            $chunkId = $metas['pendingAvailableKeyFrameInfo'][0]['nextChunkId'];
        }
        else {
            // If chunk is in the middle of keyframe (we take the next chunk)
            $found = false;
            foreach ($metas['pendingAvailableKeyFrameInfo'] as $keyframe) {
                if ($keyframe['nextChunkId'] == $chunkId) {
                    $found = true;

                    break;
                }
            }

            if (!$found) {
                $chunkId++;
            }
        }

        return $chunkId;
    }

    /**
     * @param array $metas
     * @param int   $chunkId
     * @param bool  $throwException
     *
     * @return mixed
     *
     * @throws \RuntimeException
     */
    public function findKeyframeByChunkId(array $metas, $chunkId, $throwException = false)
    {
        foreach ($metas['pendingAvailableKeyFrameInfo'] as $keyframe) {
            if ($chunkId == $keyframe['nextChunkId']) {
                return $keyframe['id'];
            }
        }

        if ($throwException) {
            throw new \RuntimeException('No keyframe found for chunk #' . ($chunkId + 1));
        }

        return $this->findKeyframeByChunkId($metas, $chunkId - 1, true);
    }

    /**
     * @param string $region
     * @param int    $gameId
     *
     * @return string
     */
    public function getReplayDirPath($region, $gameId)
    {
        $stringGameId = (string) $gameId;

        return sprintf('%s/%s/%s/%s/%s/%d', $this->path, $region, $stringGameId[0] . $stringGameId[1], $stringGameId[2], $stringGameId[3], $gameId);
    }

    /**
     * @return array
     */
    protected function getDefaultOptions()
    {
        return [
            'php.executable_path' => 'php',
            'elogank.commands.console_path' => '',
            'replay.decoder.enable' => true,
            'replay.decoder.save_files' => true
        ];
    }
} 