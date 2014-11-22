<?php

/*
 * This file is part of the "EloGank League of Legends Replay Downloader" package.
 *
 * https://github.com/EloGank/lol-replay-downloader
 *
 * For the full license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace EloGank\Replay\Crypt;

use EloGank\Replay\Replay;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@gmail.com>
 */
class ReplayCrypt
{
    /**
     * @var Replay
     */
    protected $replay;


    /**
     * @param Replay $replay
     */
    public function __construct(Replay $replay)
    {
        $this->replay = $replay;
    }

    /**
     * @param Replay $replay
     * @param string $pathFolder
     * @param int    $fileId
     * @param bool   $saveFile
     *
     * @return string
     */
    public function getBinary(Replay $replay, $pathFolder, $fileId, $saveFile = false)
    {
        $path = $pathFolder . '/' . $fileId;
        if (!is_file($path)) {
            return false;
        }

        $decodedKey = base64_decode($replay->getEncryptionKey());
        $decodedKey = $this->decrypt($replay->getGameId(), $decodedKey);

        $keyframeBinary = file_get_contents($path);
        $decodedKeyframeBinary = gzdecode($this->decrypt($decodedKey, $keyframeBinary));

        // File should saved only in development environment.
        // If parsing is needed, you have to do it in the ReplayDownloader::onReplayFileDecrypted() method
        if ($saveFile) {
            file_put_contents($pathFolder . '.decoded/' . $fileId, $decodedKeyframeBinary);
        }

        return $decodedKeyframeBinary;
    }

    /**
     * @param string $key
     * @param string $text
     *
     * @return string
     */
    protected function encrypt($key, $text)
    {
        $size = mcrypt_get_block_size('blowfish', 'ecb');
        $input = $this->pkcs5_pad($text, $size);
        $td = mcrypt_module_open('blowfish', '', 'ecb', '');
        $iv = mcrypt_create_iv (mcrypt_enc_get_iv_size($td), MCRYPT_RAND);
        mcrypt_generic_init($td, $key , $iv);

        $data = mcrypt_generic($td, $input);
        mcrypt_generic_deinit($td);
        mcrypt_module_close($td);

        return bin2hex($data);
    }

    /**
     * @param string $key
     * @param string $text
     *
     * @return bool|string
     */
    protected function decrypt($key, $text)
    {
        $td = mcrypt_module_open('blowfish', '', 'ecb', '');
        $iv = mcrypt_create_iv (mcrypt_enc_get_iv_size($td), MCRYPT_RAND);
        mcrypt_generic_init($td, $key , $iv);

        $data = mdecrypt_generic($td, $text);
        mcrypt_generic_deinit($td);
        mcrypt_module_close($td);

        $data = $this->pkcs5_unpad($data);

        return $data;
    }

    /**
     * @param string $string
     *
     * @return string
     */
    protected function pkcs5_pad($string)
    {
        $blocksize = mcrypt_get_block_size('blowfish', 'ecb');
        $pad = $blocksize - (mb_strlen($string) % $blocksize);

        return $string . str_repeat(chr($pad), $pad);
    }

    /**
     * @param string $string
     *
     * @return bool|string
     */
    protected function pkcs5_unpad($string)
    {
        $pad = ord($string{strlen($string)-1});
        if ($pad > strlen($string)) {
            return false;
        }

        if (strspn($string, chr($pad), strlen($string) - $pad) != $pad) {
            return false;
        }

        return substr($string, 0, -1 * $pad);
    }
} 