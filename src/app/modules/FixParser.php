<?php
namespace app\modules;

use httpclient;
use Throwable;
use php\io\IOException;
use framework;
use std;

class FixParser 
{
    static function parseDlls($path)
    {
        $files = fs::scan($path,['excludeDirs'=>true,'namePattern'=>
                '(?i)^(emp|custom)\.dll$|^win.*\.dll$|^(online|steam).*\.(dll|ini|json)$|^eos.*\.dll$|^epicfix.*\.dll$|^(winmm|dlllist)\.txt$']);
        if ($files == null or $files == [])
            return;
            
        foreach ($files as $file)
        {
            $regexFile = fs::name($file);
            if (Regex::match('(?i)^(winmm|dlllist)\.txt$',$regexFile))
            {
                $dlls = str::split(file_get_contents($file),"\n");
                foreach ($dlls as $dll)
                {
                    if (fs::ext($dll) != 'dll')
                        continue;
                    
                    $dll = str::lower(fs::nameNoExt(str::replace($dll,'\\','/')));
                    if (str::contains($overrides,$dll) == false)
                        $overrides .= $dll.'=n;';
                }
                
                continue;
            }
            elseif (Regex::match('(?i)^(online|steam)fix\.ini$',$regexFile))
            {
                $ini = new IniStorage($file);
                $realAppID = $ini->get('RealAppId','OnlineFix Linux') ?? $ini->get('RealAppId','Main');
                $fakeAppID = $ini->get('FakeAppId','Main');
                
                if (str::lower($regexFile) == 'steamfix.ini' and arr::has($ini->sections(),'OnlineFix Linux') == false)
                {
                    $ini->set('RealAppId',$fakeAppID,'Main');
                    $ini->set('RealAppId',$realAppID,'OnlineFix Linux');
                    
                    Logger::info('FreeTP patch applied!');
                }
                
                $ini->free();
                continue;
            }
            elseif (Regex::match('(?i)^steamfix.*\.dll',$regexFile))
            {
                $fixPath = fs::parent($file);
            }
            elseif (str::lower($regexFile) == 'onlinefix.json' and fs::isFile(fs::parent($file).'/Launcher.exe'))
            {
                $newton = fs::scan($path,['excludeDirs','namePattern'=>'^Newtonsoft\.Json\.(dll|pdb)$']);
                if ($newton == [])
                {
                    Logger::warn('Photon Launcher detected, but no Newtonsoft libraries found. Skipping patching');
                    continue;
                }
                
                foreach ($newton as $lib)
                {
                    if ($lib->getAbsolutePath() != $lib->getCanonicalPath())
                        continue;
                    
                    new Process(['ln','-s',$lib,fs::parent($file).'/'.fs::name($lib)])->startAndWait();
                }
                
                Logger::info('Photon Launcher patch applied!');
                continue;
            }
            
            $dll = str::lower(fs::nameNoExt($file));
            if (str::contains($overrides,$dll) == false)
            {
                if (Regex::match('(?i)^win.*\.dll$',$regexFile))
                    $override = '=n,b;';
                else 
                    $override = '=n;';
                $overrides .= $dll.$override;
            }
        }
        if (str::endsWith($overrides,';'))
            $overrides = str::sub($overrides,0,str::length($overrides) - 1);
        
        return ['overrides'=>$overrides,'realAppId'=>$realAppID,'fakeAppId'=>$fakeAppID,'fixPath'=>$fixPath];
    }
    
    static function parseBanner($appId)
    {
        $imagesDir = System::getProperty('user.home').'/.config/OFME-Linux/banners';
        $imagePath = $imagesDir.'/'.$appId.'.jpg';
        
        $httpDownloader = new HttpDownloader;
        
        fs::makeDir($imagesDir);
        
        Logger::info('Trying to fetch banner from akamai CDN');
        
        $result = $httpDownloader->download("https://cdn.akamai.steamstatic.com/steam/apps/$appId/header.jpg",$imagePath);
        if ($result->isError())
        {
            Logger::error('Failed to fetch banner, status code - '.$result->statusCode());
            
            fs::delete($imagePath);
            return sprintf(Localization::getByCode('BANNEREDITOR.FILE.FAILED'),$result->statusMessage().' ('.$result->statusCode().')');
        }
        
        Logger::info('Banner fetched');
        return $imagePath;
    }
    
    static function parseIcon($executable)
    {
        fs::makeDir('/tmp/OFME-icon');
        if (fs::isFile('/usr/bin/icoextract'))
        {
            Logger::info('Using icoextract instead of 7zip');
        
            $extractor = new Process(['icoextract',$executable,'/tmp/OFME-icon/icon.ico'])->startAndWait();
            $largestFile = '/tmp/OFME-icon/icon.ico';
            $iconsPath = '/tmp/OFME-icon';
        }
        else
            $extractor = new Process([FilesWorker::getThirdParty('7zip'),'-y','x',$executable,'.rsrc/ICON'],'/tmp/OFME-icon')->startAndWait();
        
        if (File::of('/tmp/OFME-icon')->findFiles() == [] or $extractor->getExitValue() != 0)
            return null;
        
        
        if ($largestFile == null)
        {
            $iconsPath = File::of('/tmp/OFME-icon/.rsrc/ICON');
            foreach ($iconsPath->findFiles() as $file)
            {
                $fileSize = $file->length();
                
                if ($fileSize > $largestFileSize)
                {
                    $largestFileSize = $fileSize;
                    $largestFile = $file;
                }
            }
        }
        
        if (fs::ext($largestFile) == 'ico' and fs::isFile('/usr/bin/ffmpeg') == false)
            throw new IOException(Localization::getByCode('FFMPEG.NOTFOUND'));
        elseif (fs::ext($largestFile) == 'ico')
        {
            $convertedPath = $iconsPath.'/'.fs::nameNoExt($largestFile).'.png';
            new Process(['ffmpeg','-y','-i',$largestFile,$convertedPath])->startAndWait();
            
            $largestFile = $convertedPath;
        }
        
        $iconsLauncherPath = System::getProperty('user.home').'/.config/OFME-Linux/icons';
        $iconName = str::random();
        $iconPath = "$iconsLauncherPath/$iconName";
        while (fs::isFile($iconPath))
        {
            $iconName = str::random();
            $iconPath = "$iconsLauncherPath/$iconName";
        }
    
        fs::makeDir($iconsLauncherPath);
        fs::copy($largestFile,$iconPath);
        
        fs::clean('/tmp/OFME-icon');
        fs::delete('/tmp/OFME-icon');
        
        return $iconPath;
    }
}