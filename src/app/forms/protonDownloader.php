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
        if ($url == null)
        {
            $this->toast(Localization::getByCode('PROTONDOWNLOADER.NOURL'));
            waitAsync('2s',function (){$this->hide();});
            return;
        }
        
        $this->title = $name;
        
        $downloader = new HttpDownloader;
        $downloader->urls = [$url];
        $downloader->destDirectory = './protons';
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
        $downloader->on('successOne',function ($e) use ($name,$timer,$downloader)
        {
            $timer->cancel();
            $downloader->free();
            
            $this->progressBar->progress = -1;
            $this->label->text = Localization::getByCode('PROTONDOWNLOADER.UNPACKING');
            
            if (fs::isFile('/usr/bin/tar') == false)
            {
                UXDialog::showAndWait(Localization::getByCode('PROTONDOWNLOADER.NOTAR'));
                $this->hide();
                return;
            }
            
            new Thread(function () use ($e,$name)
            {
                new Process(['tar','-xzf',fs::name($e->file)],fs::abs('./protons'))->startAndWait();
                fs::delete($e->file);
                
                uiLater(function () use ($name)
                {
                    $this->hide();
                    
                    app()->form('gameSettings')->installedProtons->items->add($name);
                    app()->form('gameSettings')->availableProtons->items->remove($name);
                });
            })->start();
        });
        $downloader->on('errorOne',function () use ($downloader)
        {
            UXDialog::showAndWait(Localization::getByCode('PROTONDOWNLOADER.ERRORDOWNLOADING'),'ERROR');
            
            $downloader->free();
            $this->hide();
        });
        
        $this->downloader = $downloader;
        fs::makeDir('./protons');
        $downloader->start();
    }
}
