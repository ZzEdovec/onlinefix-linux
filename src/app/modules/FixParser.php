<?php
namespace app\modules;

use Throwable;
use php\io\IOException;
use framework;
use std;
use php\jsoup\Jsoup;

class FixParser 
{
    static function parseDlls($files)
    {
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
            elseif (Regex::match('(?i)^(online|steam).*fix\.ini$',$regexFile))
            {
                $ini = new IniStorage($file);
                $realAppID = $ini->get('RealAppId','Main');
                $fakeAppID = $ini->get('FakeAppId','Main');
                
                $ini->free();
                continue;
            }
            elseif ($regexFile == 'FreeTP.Org.url')
            {
                $isFTP = true;
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
        
        return ['overrides'=>$overrides,'realAppId'=>$realAppID,'fakeAppId'=>$fakeAppID,'isFreeTP'=>$isFTP];
    }
    
    static function parseBanner($appId)
    {
        $imagesDir = System::getProperty('user.home').'/.config/OFME-Linux/banners';
        $imagePath = $imagesDir.'/'.$appId.'.jpg';
        
        fs::makeDir($imagesDir);
        
        try
        {
            Logger::info('Trying to fetch banner from akamai CDN');
            
            fs::copy("https://cdn.akamai.steamstatic.com/steam/apps/$appId/capsule_616x353.jpg",$imagePath);
        } catch (Throwable $ex) #Fallback to legacy method
        { 
            Logger::warn('Exception catched - '.$ex->getMessage().'. Fallback to legacy method');
            
            try
            {
                $jsoup = Jsoup::connect('https://store.steampowered.com/app/'.$appId)->get();
                $imageUrl = $jsoup->select('#gameHeaderImageCtn > img')->attr('src');
                
                fs::copy($imageUrl,$imagePath);
            } 
            catch (Throwable $ex)
            {
                Logger::error('Failed to fetch banner');
                
                return null;
            }
        }
        
        Logger::info('Banner fetched');
        return $imagePath;
    }
    
    static function parseIcon($executable)
    {
        $sz = File::of('./7zip/7z');
        if ($sz->canExecute() == false)
            $sz->setExecutable(true);
            
        fs::makeDir('/tmp/OFME-icon');
        $extractor = new Process([$sz->getAbsolutePath(),'-y','x',$executable,'.rsrc/ICON'],'/tmp/OFME-icon')->startAndWait();
        
        if (str::contains($extractor->getInput()->readFully(),'No files to process'))
            return null;
        
        
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
        
        if (fs::ext($largestFile) == 'ico' and fs::isFile('/usr/bin/ffmpeg') == false)
            throw new IOException(Localization::getByCode('FFMPEG.NOTFOUND'));
        elseif (fs::ext($largestFile) == 'ico')
        {
            $convertedPath = $iconsPath.'/'.fs::nameNoExt($largestFile).'.png';
            new Process(['ffmpeg','-y','-i',$largestFile,$convertedPath])->startAndWait();
            
            $largestFile = $convertedPath;
        }
        
        return $largestFile;
    }
}