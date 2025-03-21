<?php
namespace app\modules;

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
            if (fs::name($file) == 'dlllist.txt')
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
            elseif (str::endsWith($file,'.ini'))
            {
                $ini = new IniStorage($file);
                $realAppID = $ini->get('RealAppId','Main');
                
                $ini->free();
                continue;
            }
            
            $dll = str::lower(fs::nameNoExt($file));
            if (str::contains($overrides,$dll) == false)
            {
                if ($dll == 'winmm' or $dll == 'winhttp')
                    $override = '=n,b;';
                else 
                    $override = '=n;';
                $overrides .= $dll.$override;
            }
        }
        if (str::endsWith($overrides,';'))
            $overrides = str::sub($overrides,0,str::length($overrides) - 1);
        
        return ['overrides'=>$overrides,'realAppId'=>$realAppID];
    }
    
    static function parseBanner($appId)
    {
        $jsoup = Jsoup::connect('https://store.steampowered.com/app/'.$appId)->get();
        $banner = $jsoup->select('#gameHeaderImageCtn > img')->attr('src');
        
        if ($banner == null)
            throw new IOException;
        
        return $banner;
    }
    
    static function parseIcon($executable)
    {
        if (fs::isFile('/usr/bin/7z') == false)
            throw new IOException(Localization::getByCode('7Z.NOTFOUND'));
        
        fs::makeDir('/tmp/OFME-icon');
        $extractor = new Process(['7z','-y','x',$executable,'.rsrc/ICON'],'/tmp/OFME-icon')->startAndWait();
        
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