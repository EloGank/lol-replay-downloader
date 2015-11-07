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

use EloGank\Replay\Downloader\Client\ReplayClient;
use EloGank\Replay\Crypt\ReplayCrypt;
use EloGank\Replay\Downloader\Exception\GameEndedException;
use EloGank\Replay\Downloader\Exception\GameNotFoundException;
use EloGank\Replay\Downloader\Exception\GameNotStartedException;
use EloGank\Replay\Downloader\Exception\ReplayFolderAlreadyExistsException;
use EloGank\Replay\Replay;
use EloGank\Replay\Output\OutputInterface;
use EloGank\Replay\ReplayInterface;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@gmail.com>
 */
class ReplayDownloader
{
    const FILETYPE_KEYFRAME = 0;
    const FILETYPE_CHUNK    = 1;

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
        $this->client  = $client;
        $this->path    = $path;
        $this->options = array_merge($this->getDefaultOptions(), $options);
    }

    /**
     * Download a replay.
     *
     * If the asynchronous parameter is set to "true", make sure the CLI dependency is installed :
     * @see https://github.com/EloGank/lol-replay-downloader-cli
     *
     * @param string               $region
     * @param int                  $gameId
     * @param string               $encryptionKey
     * @param null|OutputInterface $output
     * @param bool                 $isOverride    If the replay folder already exists, override it (causes loss of previous files !)
     * @param bool                 $isAsync       True if the download will be in asynchronous mode, false otherwise
     * @param string               $consolePath   The absolute path to the "console" file in the CLI dependency folder
     *
     * @return Replay
     *
     * @throws GameNotFoundException
     */
    public function download($region, $gameId, $encryptionKey, OutputInterface $output = null, $isOverride = false, $isAsync = false, $consolePath = null)
    {
        // Check if exists
        if (!$this->client->isGameExists($region, $gameId)) {
            throw new GameNotFoundException('The game #' . $gameId . ' is not found');
        }

        // Create directories
        if ($isOverride) {
            if (!is_dir($this->getReplayDirPath($region, $gameId))) {
                $this->createDirs($region, $gameId);
            }
        }
        else {
            $this->createDirs($region, $gameId);
        }

        if ($isAsync) {
            // Check for the CLI dependency
            if (!class_exists('\EloGank\Replay\Command\ReplayDownloadCommand')) {
                throw new \RuntimeException('The dependency to run the async download is missing. Please, see : https://github.com/EloGank/lol-replay-downloader-cli');
            }

            $replayFolder = $this->getReplayDirPath($region, $gameId);

            return pclose(popen(sprintf('%s %s/console elogank:replay:download %s %d %s --override > %s/info.log 2>&1 & echo $! > %s/pid', $this->options['php.executable_path'], $consolePath, $region, $gameId, $encryptionKey, $replayFolder, $replayFolder), 'r'));
        }

        return $this->doDownload($region, $gameId, $encryptionKey, $output);
    }

    /**
     * @param string               $region
     * @param int                  $gameId
     * @param string               $encryptionKey
     * @param null|OutputInterface $output
     *
     * @return ReplayInterface
     *
     * @throws GameEndedException
     * @throws GameNotStartedException
     */
    protected function doDownload($region, $gameId, $encryptionKey, OutputInterface $output = null)
    {
        $hasOutput = null != $output;
        $replay = $this->createReplay($region, $gameId, $encryptionKey);

        // Download metas
        $this->downloadMetas($replay, $output);

        // Validate game criterias based on metas
        $this->isValid($replay, $output);

        // Retrieve last infos to download previous files
        if ($hasOutput) {
            $output->write("Retrieve last infos...\t\t\t");
        }

        $lastChunkInfo = $this->getLastChunkInfos($replay);
        $replay->setLastChunkId($lastChunkInfo['chunkId']);
        $replay->setLastKeyframeId($lastChunkInfo['keyFrameId']);

        if ($hasOutput) {
            $output->writeln('<info>OK</info>');
        }

        // Download previous chunks
        if ($hasOutput) {
            $output->write("Download all previous chunks (" . $replay->getLastChunkId() . ")...\t");
        }

        $this->downloadChunks($replay);

        if ($hasOutput) {
            $output->writeln('<info>OK</info>');
        }

        // Download previous keyframes
        if ($hasOutput) {
            $output->write("Download all previous keyframes (" . $replay->getLastKeyframeId() . ")...\t");
        }

        $this->downloadKeyframes($replay, $output);

        if ($hasOutput) {
            $output->writeln(array('<info>OK</info>', ''));
        }

        // Download current chunks & keyframes
        if ($hasOutput) {
            $output->writeln("Download current game data :");
        }

        $this->downloadCurrentData($replay, $output);

        if ($hasOutput) {
            $output->writeln('');
        }

        // Update metas
        if ($hasOutput) {
            $output->write("Update metas...\t\t\t\t");
        }

        $this->updateMetas($replay);

        if ($hasOutput) {
            $output->writeln('<info>OK</info>');
        }

        return $replay;
    }

    /**
     * @param string $region
     * @param int    $gameId
     * @param string $encryptionKey
     *
     * @return ReplayInterface
     */
    public function createReplay($region, $gameId, $encryptionKey)
    {
        $className = $this->options['replay.class'];
        $replay = new $className($region, $gameId, $encryptionKey);

        if (!$replay instanceof ReplayInterface) {
            throw new \RuntimeException('The class ' . $className . ' is not a valid class. It should implement \EloGank\Replay\ReplayInterface');
        }

        return $replay;
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
     * @param ReplayInterface $replay
     * @param OutputInterface $output
     * @param int             $tries
     *
     * @return bool
     *
     * @throws GameEndedException
     * @throws GameNotStartedException
     */
    public function isValid(ReplayInterface $replay, OutputInterface $output = null, $tries = 0)
    {
        $hasOutput = null != $output;
        if ($hasOutput) {
            $output->write("Validate game criterias...\t\t");
        }

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

        $size = sizeof($metas);

        // Retries
        if (1 < $size && !isset($metas['pendingAvailableKeyFrameInfo'][0])) {
            if ($hasOutput && 0 === $tries) {
                $output->writeln('<comment>Game not started</comment>');
                $output->write("Waiting for the ingame ~3rd minute...\t");
            }

            if ($tries < 20) {
                sleep(30);
                ++$tries;

                return $this->isValid($replay, $output, $tries);
            }

            if ($hasOutput) {
                $output->write('<error>FAILURE</error>');
            }

            throw new GameNotStartedException('The game is not yet available for spectator, please wait tree minutes');
        }

        if (1 == $size && isset($metas['encryptionKey']) || $metas['gameEnded']) {
            if ($hasOutput) {
                $output->write('<error>FAILURE</error>');
            }

            throw new GameEndedException('The game has already ended, cannot download it');
        }

        if ($hasOutput) {
            $output->writeln('<info>OK</info>');
        }

        return true;
    }

    /**
     * @param ReplayInterface $replay
     *
     * @return array
     */
    public function downloadMetas(ReplayInterface $replay, OutputInterface $output = null)
    {
        $hasOutput = null != $output;
        if ($hasOutput) {
            $output->write("Retrieve metas...\t\t\t");
        }

        $gameId = $replay->getGameId();
        $metas = $this->client->getMetas($replay->getRegion(), $gameId);

        // Update replay object
        $metas = json_decode($metas, true);
        $metas['encryptionKey'] = $replay->getEncryptionKey();
        $replay->setMetas($metas);

        if ($hasOutput) {
            $output->writeln('<info>OK</info>');
        }

        return $metas;
    }

    /**
     * @param ReplayInterface $replay
     */
    public function saveMetas(ReplayInterface $replay)
    {
        // Save the file
        file_put_contents($this->getReplayDirPath($replay->getRegion(), $replay->getGameId()) . '/metas.json', json_encode($replay->getMetas()));
    }

    /**
     * @param ReplayInterface $replay
     * @param int             $chunkId
     * @param int             $tries
     *
     * @return mixed
     *
     * @throws GameNotStartedException
     */
    public function getLastChunkInfos(ReplayInterface $replay, $chunkId = 30000, $tries = 0)
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
     * @param ReplayInterface $replay
     */
    public function downloadChunks(ReplayInterface $replay)
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
     * @param ReplayInterface $replay
     * @param OutputInterface $output
     */
    public function downloadKeyframes(ReplayInterface $replay, OutputInterface $output)
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
     * @param ReplayInterface $replay
     * @param int             $chunkId
     *
     * @return bool
     */
    private function downloadChunk(ReplayInterface $replay, $chunkId)
    {
        $chunk = $this->client->downloadChunk($replay->getRegion(), $replay->getGameId(), $chunkId);
        if (false === $chunk) {
            try {
                if ($chunkId > $this->findFirstChunkId($replay->getMetas()) && isset($replay->getMetas()['pendingAvailableChunkInfo'][0])) {
                    $replay->addDownloadRetry();
                    if ($this->options['replay.download.retry'] == $replay->getDownloadRetry()) {
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

        $pathFolder = $this->getReplayDirPath($replay->getRegion(), $replay->getGameId()) . '/chunks';
        file_put_contents($pathFolder . '/' . $chunkId, $chunk);

        // Update metas
        $metas = $replay->getMetas();
        $metas['pendingAvailableChunkInfo'][] = array(
            'duration'     => 30000,
            'id'           => $chunkId,
            'receivedTime' => date('M n, Y g:i:s A')
        );
        $replay->setMetas($metas);

        if ($this->options['replay.decoder.enable']) {
            $crypt = new ReplayCrypt($replay);

            if (!$this->onReplayFileDecrypted($replay, self::FILETYPE_CHUNK, $chunkId,
                $crypt->getBinary($replay, $pathFolder, $chunkId, $this->options['replay.decoder.save_files'])
            )) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param ReplayInterface $replay
     * @param int             $keyframeId
     *
     * @return bool
     */
    private function downloadKeyframe(ReplayInterface $replay, $keyframeId)
    {
        $keyframe = $this->client->downloadKeyframe($replay->getRegion(), $replay->getGameId(), $keyframeId);
        if (false === $keyframe) {
            try {
                if ($keyframeId > $this->findKeyframeByChunkId($replay->getMetas(), $this->findFirstChunkId($replay->getMetas())) &&
                    isset($replay->getMetas()['pendingAvailableKeyFrameInfo'][0])) {
                    $replay->addDownloadRetry();
                    if ($this->options['replay.download.retry'] == $replay->getDownloadRetry()) {
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
                $crypt->getBinary($replay, $pathFolder, $keyframeId, $this->options['replay.decoder.save_files'])
            )) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param ReplayInterface $replay   The replay
     * @param string          $fileType Will be self::FILETYPE_KEYFRAME or self::FILETYPE_CHUNK
     * @param int             $fileId   The file id (which is the filename)
     * @param string          $binary   The decrypted file binary
     *
     * @return bool True if success, false otherwise.
     */
    protected function onReplayFileDecrypted(ReplayInterface $replay, $fileType, $fileId, $binary)
    {
        // If you have to parse a decrypted file, do it here.

        return true;
    }

    /**
     * @param ReplayInterface $replay
     * @param OutputInterface $output
     *
     * @return bool
     */
    public function downloadCurrentData(ReplayInterface $replay, OutputInterface $output = null)
    {
        $hasOutput = null != $output;
        $lastInfos = $this->getLastChunkInfos($replay, $replay->getLastChunkId());

        // End stats
        if ($lastInfos['endGameChunkId'] == $replay->getLastChunkId()) {
            return true;
        }

        $downloadableChunkId = $replay->getLastChunkId() + 1;
        if ($hasOutput) {
            $output->write("Downloading chunk\t#" . $downloadableChunkId . "\t\t");
        }

        $this->downloadChunk($replay, $downloadableChunkId);
        $replay->setLastChunkId($downloadableChunkId);

        if ($hasOutput) {
            $output->writeln('<info>OK</info>');
        }

        if ($lastInfos['keyFrameId'] > $replay->getLastKeyframeId()) {
            $downloadableKeyframeId = $replay->getLastKeyframeId() + 1;
            if ($hasOutput) {
                $output->write("Downloading keyframe\t#" . $downloadableKeyframeId . "\t\t");
            }

            $this->downloadKeyframe($replay, $downloadableKeyframeId, $output);
            $replay->setLastKeyframeId($downloadableKeyframeId);

            if ($hasOutput) {
                $output->writeln('<info>OK</info>');
            }
        }

        // Downloading all chunks & keyframes might slow the current chunk
        if ($lastInfos['chunkId'] > $replay->getLastChunkId() || $lastInfos['keyFrameId'] > $replay->getLastKeyframeId()) {
            return $this->downloadCurrentData($replay, $output);
        }

        // Wait for the next available chunk
        usleep(($lastInfos['nextAvailableChunk'] + 500) * 1000); // micro, not milli

        // Free memory to avoid memory leak
        gc_collect_cycles();

        // And again
        $this->downloadCurrentData($replay, $output);
    }

    /**
     * @param ReplayInterface $replay
     */
    public function updateMetas(ReplayInterface $replay)
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
            'php.executable_path'           => 'php',
            'replay.class'                  => '\EloGank\Replay\Replay',
            'replay.decoder.enable'         => false,
            'replay.decoder.save_files'     => false,
            'replay.download.retry'         => 15,
        ];
    }
}
