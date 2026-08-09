<?php
namespace app\modules;

use std, gui, framework, app;
use httpclient;

class AriaDownloader
{
    static $busy = false;
    static $nextToken = 0;
    static $activeToken = null;

    static function isBusy()
    {
        return self::$busy;
    }

    static $aria2Path = null;

    static function findAria2()
    {
        if (self::$aria2Path == null)
        {
            $result = execute('which aria2c', true);
            $output = $result->getInput()->readFully();
            self::$aria2Path = str::trim($output);
        }
        return self::$aria2Path;
    }

    static function isAvailable()
    {
        return str::length(self::findAria2()) > 0;
    }

    static function fetchSource($url, $callback)
    {
        new Thread(function () use ($url, $callback)
        {
            try
            {
                $client = new HttpClient;
                $client->connectTimeout = 10000;
                $client->readTimeout = 15000;

                $response = $client->get($url);
                if ($response->isFail())
                {
                    $statusCode = $response->statusCode();
                    uiLater(function () use ($callback, $statusCode) { $callback(null, 'HTTP ' . $statusCode); });
                    return;
                }

                $body = $response->body();
                $json = \facade\Json::decode($body);
                if ($json == null || !isset($json['downloads']) || !is_array($json['downloads']))
                {
                    uiLater(function () use ($callback) { $callback(null, 'Invalid source format'); });
                    return;
                }

                uiLater(function () use ($json, $callback) { $callback($json, null); });
            }
            catch (\Throwable $ex)
            {
                uiLater(function () use ($callback, $ex) { $callback(null, $ex->getMessage()); });
            }
        })->start();
    }

    static function startDownload($magnetUri, $destDir, $onProgress, $onStatus, $onComplete, $onError)
    {
        if (self::$busy)
        {
            uiLater(function () use ($onError) { $onError('Another download is already in progress'); });
            return null;
        }

        $aria2Path = self::findAria2();
        if (str::length($aria2Path) == 0)
        {
            uiLater(function () use ($onError) { $onError('aria2c not found'); });
            return null;
        }

        $token = ++self::$nextToken;
        self::$activeToken = $token;
        self::$busy = true;

        try
        {
            if (!fs::isDir($destDir))
            {
                fs::ensureParent($destDir);
                fs::makeDir($destDir);
            }
        }
        catch (\Throwable $ex)
        {
            self::$busy = false;
            self::$activeToken = null;
            uiLater(function () use ($onError, $ex) { $onError($ex->getMessage()); });
            return null;
        }

        $args = [
            $aria2Path,
            '--dir=' . $destDir,
            '--bt-stop-timeout=300',
            '--seed-time=0',
            '--bt-remove-unselected-file=true',
            '--summary-interval=1',
            '--enable-dht=true',
            '--dht-listen-port=6881',
            '--listen-port=6881',
            '--max-concurrent-downloads=1',
            '--max-overall-download-limit=0',
            '--file-allocation=none',
            '--auto-file-renaming=false',
            '--allow-overwrite=true',
            '--quiet=false',
            '--enable-rpc=false',
            $magnetUri
        ];

        try
        {
            $process = new Process($args, $destDir)->start();
        }
        catch (\Throwable $ex)
        {
            self::$busy = false;
            self::$activeToken = null;
            uiLater(function () use ($onError, $ex) { $onError($ex->getMessage()); });
            return null;
        }

        $handle = ['process' => $process, 'timer' => null, 'token' => $token];

        new Thread(function () use ($process, $onProgress, $onStatus)
        {
            try
            {
                $process->getInput()->eachLine(function ($line) use ($onProgress, $onStatus)
                {
                    $line = str::trim($line);
                    if (str::length($line) == 0)
                        return false;

                    if (str::contains($line, 'Download complete'))
                        return true;

                    if (Regex::match('(\d{1,3})\s*%', $line))
                    {
                        $m = Regex::of('(\d{1,3})\s*%', null, $line)->one();
                        if (isset($m[1]))
                        {
                            $pct = min(100, max(0, intval($m[1])));
                            uiLater(function () use ($onProgress, $pct, $line) { $onProgress($line, $pct); });
                        }
                        return false;
                    }

                    if (Regex::match('\(([\d.]+\s*[KMGT]?i?B?)\/([\d.]+\s*[KMGT]?i?B?)\)', $line))
                    {
                        uiLater(function () use ($onProgress, $line) { $onProgress($line); });
                        return false;
                    }

                    if (str::contains($line, 'Fetching metadata'))
                    {
                        uiLater(function () use ($onStatus) { $onStatus('fetching'); });
                        return false;
                    }

                    if (str::contains($line, 'Connecting to peers'))
                    {
                        uiLater(function () use ($onStatus) { $onStatus('connecting'); });
                        return false;
                    }

                    return false;
                });
            } catch (\Throwable $ex)
            {
                Logger::error('AriaDownloader reader: ' . $ex->getMessage());
            }
        })->start();

        new Thread(function () use ($process)
        {
            try
            {
                $process->getError()->eachLine(function ($line) { return false; });
            }
            catch (\Throwable $ex) {}
        })->start();

        $handle['timer'] = Timer::every('1s', function () use ($process, $onComplete, $onError, $token, &$handle)
        {
            try
            {
                if (self::$activeToken != $token)
                {
                    $handle['timer']->cancel();
                    return;
                }

                if ($process->isAlive() == false)
                {
                    $handle['timer']->cancel();
                    self::$busy = false;
                    self::$activeToken = null;

                    $exitCode = $process->getExitValue();
                    if ($exitCode == 0)
                        uiLater(function () use ($onComplete) { $onComplete(); });
                    else
                    {
                        $msg = self::getExitError($exitCode);
                        uiLater(function () use ($onError, $msg) { $onError($msg); });
                    }
                }
            }
            catch (\Throwable $ex)
            {
                try { $handle['timer']->cancel(); } catch (\Throwable $ex2) {}
                self::$busy = false;
                self::$activeToken = null;
                uiLater(function () use ($onError, $ex) { $onError($ex->getMessage()); });
            }
        });

        return $handle;
    }

    static function getExitError($exitCode)
    {
        try
        {
            $localized = app()->module('Localization')->_('ARIA.EXITCODE.' . $exitCode);
            if (str::startsWith($localized, 'FAILED TO LOAD ') == false)
                return $localized;
        }
        catch (\Throwable $ex) {}

        $signals = [
            130 => 'Aria2 was terminated by signal',
            137 => 'Aria2 was killed',
            143 => 'Aria2 was terminated'
        ];
        return $signals[$exitCode] ?? "Exit code: $exitCode";
    }

    static function cancelDownload($handle)
    {
        if ($handle == null)
            return;

        if (isset($handle['token']) and self::$activeToken == $handle['token'])
        {
            self::$busy = false;
            self::$activeToken = null;
        }

        if (isset($handle['timer']) and $handle['timer'] != null)
        {
            try { $handle['timer']->cancel(); } catch (\Throwable $ex) {}
        }

        if (isset($handle['process']))
        {
            try { if ($handle['process']->isAlive()) $handle['process']->destroy(true); } catch (\Throwable $ex) {}
        }
    }

    static function removeDownload($dir)
    {
        if ($dir == null or str::length(str::trim($dir)) == 0 or fs::isDir($dir) == false)
            return false;

        $base = \app\forms\launcherSettings::getBasePathFor('downloads');
        $dir = fs::abs($dir);
        $base = fs::abs($base);
        if ($dir == $base or str::startsWith($dir, $base . '/') == false)
            return false;

        new Process(['rm','-rf',$dir])->start();
        return true;
    }

    static function findGameFiles($dir)
    {
        $files = [];
        self::collectGameFiles($dir, $files);

        $files = arr::sort($files, function ($a, $b)
        {
            $aIsPart1 = str::contains(str::lower(fs::name($a)), '.part1.');
            $bIsPart1 = str::contains(str::lower(fs::name($b)), '.part1.');
            if ($aIsPart1 != $bIsPart1)
                return $aIsPart1 ? -1 : 1;
            return str::compare($a, $b);
        });

        return $files;
    }

    static function collectGameFiles($dir, &$files)
    {
        foreach (fs::scan($dir) as $entry)
        {
            if ($entry->isDirectory())
            {
                self::collectGameFiles($entry->getPath(), $files);
                continue;
            }

            $ext = fs::ext($entry);
            if (in_array(str::lower($ext), ['exe', 'rar', 'bat', 'vbs']))
                $files[] = $entry->getPath();
        }
    }
}
