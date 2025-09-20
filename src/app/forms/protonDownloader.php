<?php
namespace app\forms;

use Throwable;
use httpclient;
use std, gui, framework, app;


class protonDownloader extends AbstractForm
{

    /**
     * @event hide 
     */
    function doHide(UXWindowEvent $e = null)
    {    
        if (isset($this->downloader) and $this->downloader->isFree() == false and $this->downloader->isBreak() == false)
            $this->downloader->stop();
    }
    
    function startDownload($name,$url)
    {
        $protonPath = launcherSettings::getBasePathFor('protons');
        
        fs::ensureParent($protonPath);
        fs::makeDir($protonPath);
        
        $isProtonPath = fs::isDir($protonPath);
        
        if ($url == null or $isProtonPath == false)
        {
            UXDialog::show($url == null ? Localization::getByCode('PROTONDOWNLOADER.NOURL') : Localization::getByCode('PROTONDOWNLOADER.NOPATH'),'ERROR');
            
            $this->hide();
            return;
        }
        
        $this->title = $name;
        
        $downloader = new HttpDownloader;
        $downloader->urls = [$url];
        $downloader->destDirectory = $protonPath;
        $downloader->threadCount = 40;
        $downloadText = Localization::getByCode('PROTONDOWNLOADER.DOWNLOADING');
        $timer = Timer::every('1s',function () use ($downloader,$downloadText)
        {
            $speed = $downloadText.' ('.round($downloader->getSpeed() / 1e+6,2).'MB/s)';
            uiLater(function () use ($speed){$this->label->text = $speed;});
        });
        $downloader->on('progress',function ($e) use ($downloader)
        {
            $this->progressBar->progress = ($e->progress / $e->max) * 100;
        });
        $downloader->on('successOne',function ($e) use ($name,$timer,$downloader,$protonPath)
        {
            $timer->cancel();
            $downloader->free();
            
            $this->progressBar->progress = -1;
            $this->label->text = Localization::getByCode('PROTONDOWNLOADER.UNPACKING');
            
            if (fs::isFile('/usr/bin/tar') == false)
            {
                UXDialog::show(Localization::getByCode('PROTONDOWNLOADER.NOTAR'),'ERROR');
                
                fs::delete($e->file);
                $this->hide();
                return;
            }
            
            new Thread(function () use ($e,$name,$protonPath)
            {
                new Process(['tar','-xzf',fs::name($e->file)],$protonPath)->startAndWait();
                fs::delete($e->file);
                
                uiLater(function () use ($name)
                {
                    $this->hide();
                });
            })->start();
        });
        $downloader->on('errorOne',function ($e) use ($downloader)
        {
            UXDialog::showAndWait(Localization::getByCode('PROTONDOWNLOADER.ERRORDOWNLOADING'),'ERROR');
            
            fs::delete($e->file);
            $downloader->free();
            $this->hide();
        });
        
        $this->downloader = $downloader;
        $downloader->start();
    }
}
