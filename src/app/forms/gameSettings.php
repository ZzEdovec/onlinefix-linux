<?php
namespace app\forms;

use Throwable;
use php\gui\animatefx\AnimationFX;
use php\gui\controlsfx\UXPopOver;
use php\gui\controlsfx\UXToggleSwitch;
use std, gui, framework, app;


class gameSettings extends AbstractForm
{

    /**
     * @event construct 
     */
    function doConstruct(UXEvent $e = null)
    {
        if (fs::isFile('/usr/bin/gamemoderun') == false)
            $this->gamemode->enabled = false;
        if (fs::isFile('/usr/bin/mangohud') == false)
           $this->mangohud->enabled = false;
        if (fs::isFile('/usr/bin/gamescope') == false)
            $this->gamescope->enabled = false;
    }

    /**
     * @event show 
     */
    function doShow(UXWindowEvent $e = null)
    {
        $this->overrides->text = $this->appModule()->games->get('overrides',$this->gameName->text);
        $this->env->text = $this->appModule()->games->get('environment',$this->gameName->text);
        $this->argsBefore->text = $this->appModule()->games->get('argsBefore',$this->gameName->text);
        $this->argsAfter->text = $this->appModule()->games->get('argsAfter',$this->gameName->text);
        
        uiLater(function ()
        {
            $this->gamemode->data('quUIElement')->selected = str::contains($this->argsBefore->text,'gamemoderun');
            $this->mangohud->data('quUIElement')->selected = str::contains($this->argsBefore->text,'mangohud');
            $this->gamescope->data('quUIElement')->selected = str::contains($this->argsBefore->text,'gamescope');
            $this->steamOverlay->data('quUIElement')->selected = $this->appModule()->games->get('steamOverlay',$this->gameName->text);
            $this->steamRuntime->data('quUIElement')->selected = $this->appModule()->games->get('steamRuntime',$this->gameName->text);
            
            if (str::contains($this->env->text,'LD_PRELOAD'))
                $this->steamOverlay->enabled = false;
        });
        
        $this->installedProtons->items->clear();
        $this->availableProtons->items->clear();
        
        $this->installedProtons->items->add('GE-Proton Latest');
        new Thread(function (){
            if (fs::isDir('./protons'))
            {
                $dir = File::of('./protons');
                $dirs = $dir->find(function ($d,$f){return fs::isFile($d.'/'.$f.'/proton');});
                
                uiLater(function () use ($dirs){$this->installedProtons->items->addAll($dirs);});
            }
            uiLater(function ()
            {
                $selectedProton = $this->appModule()->games->get('proton',$this->gameName->text);
                foreach ($this->installedProtons->items->toArray() as $num => $item)
                {
                    if ($item == $selectedProton)
                    {
                        $this->installedProtons->selectedIndex = $num;
                        break;
                    }
                }
            });
            
            $releases = filesWorker::fetchProtonReleases();
            if ($releases == false or str::contains($releases,'tar.gz'))
            {
                uiLater(function (){$this->toast(Localization::getByCode('GAMESETTINGS.NOGITHUBAPI'),'ERROR');});
                return;
            }
            
            foreach ($releases as $release)
            {
                foreach ($release['assets'] as $asset)
                {
                    if ($asset['content_type'] != 'application/gzip' or $asset['state'] != 'uploaded' or $asset['browser_download_url'] == null)
                        continue;
                    
                    $this->availableProtons->data($release['tag_name'],$asset['browser_download_url']);
                    if (str::contains($this->installedProtons->itemsText,$release['tag_name']) == false)
                        uiLater(function () use ($release){$this->availableProtons->items->add($release['tag_name']);});
                }
            }
            
            $this->availableProtons->data('releases',$releases);
        })->start();
    }












    /**
     * @event overrides.keyUp 
     */
    function doOverridesKeyUp(UXKeyEvent $e = null)
    {
        $this->appModule()->games->set('overrides',$e->sender->text,$this->gameName->text);
    }


    /**
     * @event env.keyUp 
     */
    function doEnvKeyUp(UXKeyEvent $e = null)
    {
        if (str::contains($e->sender->text,'LD_PRELOAD'))
        {
            $this->steamOverlay->enabled = false;
            
            if ($this->steamOverlay->data('quUIElement')->selected)
            {
                $this->steamOverlay->data('quUIElement')->selected = false;
                $this->appModule()->games->set('steamOverlay',false,$this->gameName->text);
            }
        }
        else 
            $this->steamOverlay->enabled = true;
        if (str::contains($e->sender->text,'WINEDLLOVERRIDES'))
            $e->sender->text = str::replace($e->sender->text,'WINEDLLOVERRIDES',null);
            
        $this->appModule()->games->set('environment',$e->sender->text,$this->gameName->text);
    }



    /**
     * @event argsBefore.keyUp 
     */
    function doArgsBeforeKeyUp(UXKeyEvent $e = null)
    {
        $this->gamemode->data('quUIElement')->selected = str::contains($e->sender->text,'gamemoderun');
        $this->mangohud->data('quUIElement')->selected = str::contains($e->sender->text,'mangohud');
        $this->gamescope->data('quUIElement')->selected = str::contains($e->sender->text,'gamescope');
        
        $this->appModule()->games->set('argsBefore',$e->sender->text,$this->gameName->text);
    }









    /**
     * @event winetricksButton.construct 
     */
    function doWinetricksButtonConstruct(UXEvent $e = null)
    {
        $e->sender->graphic = new UXImageArea(new UXImage('res://.data/img/wine.png'));
        $e->sender->graphic->size = [20,20];
        $e->sender->graphic->stretch = $e->sender->graphic->centered = $e->sender->graphic->proportional = true;
    }

    /**
     * @event gameFolderButton.construct 
     */
    function doGameFolderButtonConstruct(UXEvent $e = null)
    {
        $e->sender->text = Localization::getByCode('GAMESETTINGS.FOLDERS.BUTTON');
        $e->sender->graphic = new UXImageArea(new UXImage('res://.data/img/folder.png'));
        $e->sender->graphic->size = [20,20];
        $e->sender->graphic->stretch = $e->sender->graphic->centered = $e->sender->graphic->proportional = true;
        
        $menu = new UXContextMenu;
        $gameFolder = new UXMenuItem(Localization::getByCode('GAMESETTINGS.FOLDERS.GAME'));
        $prefixFolder = new UXMenuItem(Localization::getByCode('GAMESETTINGS.FOLDERS.PREFIX'));
        
        $gameFolder->on('action',function ()
        {
            open($this->appModule()->games->get('mainPath',$this->gameName->text) ?? fs::parent($this->appModule()->games->get('executable',$this->gameName->text)));
        });
        $prefixFolder->on('action',function ()
        {
            $prefixDir = fs::parent($this->appModule()->games->get('executable',$this->gameName->text)).'/OFME Prefix/pfx/drive_c';
            if (fs::isDir($prefixDir))
                open($prefixDir);
            else 
                $this->toast(Localization::getByCode('GAMESETTINGS.WINETRICKS.NOPREFIX'));
        });
        
        $menu->items->addAll([$gameFolder,$prefixFolder]);
        
        $e->sender->on('click',function (UXMouseEvent $e) use ($menu)
        {
            $menu->showByNode($e->sender,$e->x,$e->y);
        });
    }

    /**
     * @event overview.click 
     */
    function doOverviewClick(UXMouseEvent $e = null)
    {    
        $this->switchPage($this->overviewPage,$this->overview);
    }

    /**
     * @event envs.click 
     */
    function doEnvsClick(UXMouseEvent $e = null)
    {
        $this->switchPage($this->envsPage,$this->envs);
    }




    /**
     * @event vbox3.click 
     */
    function doVbox3Click(UXMouseEvent $e = null)
    {
        $this->env->requestFocus();
    }

    /**
     * @event label6.construct 
     */
    function doLabel6Construct(UXEvent $e = null)
    {
        $e->sender->text = Localization::getByCode('GAMESETTINGS.ENVS.ENVIRONMENT');
    }

    /**
     * @event vbox.click 
     */
    function doVboxClick(UXMouseEvent $e = null)
    {
        $this->argsBefore->requestFocus();
    }

    /**
     * @event vbox4.click 
     */
    function doVbox4Click(UXMouseEvent $e = null)
    {
        $this->argsAfter->requestFocus();
    }



    /**
     * @event mangohud.construct 
     */
    function doMangohudConstruct(UXEvent $e = null)
    {
        quUI::generateSetButton($e->sender,Localization::getByCode('GAMESETTINGS.ADDITIONALS.USEMANGOHUD'),new UXToggleSwitch);
    }

    /**
     * @event mangohud.action 
     */
    function doMangohudAction(UXEvent $e = null)
    {
        !($e->sender->data('quUIElement')->selected) == true ? $this->addBeforeArg('mangohud') : $this->removeBeforeArg('mangohud');
    }

    /**
     * @event gamescope.construct 
     */
    function doGamescopeConstruct(UXEvent $e = null)
    {
        quUI::generateSetButton($e->sender,Localization::getByCode('GAMESETTINGS.ADDITIONALS.USEGAMESCOPE'),new UXToggleSwitch);
    }

    /**
     * @event gamescope.action 
     */
    function doGamescopeAction(UXEvent $e = null)
    {
        !($e->sender->data('quUIElement')->selected) == true ? $this->addBeforeArg('gamescope --') : $this->removeBeforeArg('gamescope --');
    }

    /**
     * @event steamOverlay.construct 
     */
    function doSteamOverlayConstruct(UXEvent $e = null)
    {
        quUI::generateSetButton($e->sender,Localization::getByCode('GAMESETTINGS.ADDITIONALS.USESTEAMOVERLAY'),new UXToggleSwitch);
    }

    /**
     * @event steamRuntime.construct 
     */
    function doSteamRuntimeConstruct(UXEvent $e = null)
    {
        quUI::generateSetButton($e->sender,Localization::getByCode('GAMESETTINGS.ADDITIONALS.USESTEAMRUNTIME'),new UXToggleSwitch);
    }


    /**
     * @event vboxAlt.click 
     */
    function doVboxAltClick(UXMouseEvent $e = null)
    {
        $this->overrides->requestFocus();
    }

    /**
     * @event label.construct 
     */
    function doLabelConstruct(UXEvent $e = null)
    {
        $e->sender->text = Localization::getByCode('GAMESETTINGS.STARTSETTINGS');
    }

    /**
     * @event gamemode.construct 
     */
    function doGamemodeConstruct(UXEvent $e = null)
    {
        quUI::generateSetButton($e->sender,Localization::getByCode('GAMESETTINGS.ADDITIONALS.USEGAMEMODE'),new UXToggleSwitch);
    }

    /**
     * @event gamemode.action 
     */
    function doGamemodeAction(UXEvent $e = null)
    {
        if (!($e->sender->data('quUIElement')->selected))
        {
            $this->toast(Localization::getByCode('GAMESETTINGS.GAMEMODE.WARNING'));
            $this->addBeforeArg('gamemoderun');
        }
        else 
            $this->removeBeforeArg('gamemoderun');
    }

    /**
     * @event argsAfter.keyUp 
     */
    function doArgsAfterKeyUp(UXKeyEvent $e = null)
    {    
        $this->appModule()->games->set('argsAfter',$e->sender->text,$this->gameName->text);
    }

    /**
     * @event steamOverlay.action 
     */
    function doSteamOverlayAction(UXEvent $e = null)
    {
        if (!($e->sender->data('quUIElement')->selected))
            $this->toast(Localization::getByCode('GAMESETTINGS.STEAMOVERLAY.WARNING'));
            
        $this->appModule()->games->set('steamOverlay',!($e->sender->data('quUIElement')->selected),$this->gameName->text);
    }

    /**
     * @event steamRuntime.action 
     */
    function doSteamRuntimeAction(UXEvent $e = null)
    {
        if (filesWorker::findSteamRuntime() == false and !($e->sender->data('quUIElement')->selected))
        {
            if (uiConfirm(Localization::getByCode('GAMESETTINGS.STEAM.NORUNTIME')))
                execute('/usr/bin/steam steam://install/1628350');
            
            uiLater(function () use ($e){$e->sender->data('quUIElement')->selected = false;});
            return;
        }
        
        $this->appModule()->games->set('steamRuntime',!($e->sender->data('quUIElement')->selected),$this->gameName->text);
    }

    /**
     * @event protons.click 
     */
    function doProtonsClick(UXMouseEvent $e = null)
    {
        $this->switchPage($this->protonsPage,$this->protons);
    }

    /**
     * @event downloadButton.construct 
     */
    function doDownloadButtonConstruct(UXEvent $e = null)
    {
        $e->sender->tooltipText = Localization::getByCode('GAMESETTINGS.PROTONS.INSTALL');
        $e->sender->graphic = new UXImageArea(new UXImage('res://.data/img/left.png'));
        $e->sender->graphic->size = [15,15];
        $e->sender->graphic->stretch = $e->sender->graphic->centered = $e->sender->graphic->proportional = true;
    }

    /**
     * @event removeButton.construct 
     */
    function doRemoveButtonConstruct(UXEvent $e = null)
    {
        $e->sender->tooltipText = Localization::getByCode('GAMESETTINGS.PROTONS.REMOVE');
        $e->sender->graphic = new UXImageArea(new UXImage('res://.data/img/right.png'));
        $e->sender->graphic->size = [15,15];
        $e->sender->graphic->stretch = $e->sender->graphic->centered = $e->sender->graphic->proportional = true;
    }

    /**
     * @event downloadButton.action 
     */
    function doDownloadButtonAction(UXEvent $e = null)
    {    
        if ($this->availableProtons->selectedIndex == -1)
            return;
        
        app()->showForm('protonDownloader');
        app()->form('protonDownloader')->startDownload($this->availableProtons->selectedItem,$this->availableProtons->data($this->availableProtons->selectedItem));
    }

    /**
     * @event removeButton.action 
     */
    function doRemoveButtonAction(UXEvent $e = null)
    {    
        if ($this->installedProtons->selectedIndex == -1 or $this->installedProtons->selectedItem == 'GE-Proton Latest')
            return;
        
        if (fs::isDir('./protons/'.$this->installedProtons->selectedItem))
        {
            fs::clean('./protons/'.$this->installedProtons->selectedItem);
            fs::delete('./protons/'.$this->installedProtons->selectedItem);
        }
        
        if (str::contains($this->installedProtons->selectedItem,'GE-Proton'))
            $this->availableProtons->items->insert(0,$this->installedProtons->selectedItem);
        $this->installedProtons->items->removeByIndex($this->installedProtons->selectedIndex);
        
        uiLater(function (){$this->appModule()->games->set('proton',$this->installedProtons->selectedItem,$this->gameName->text);});
    }

    /**
     * @event installedProtons.action 
     */
    function doInstalledProtonsAction(UXEvent $e = null)
    {
        if ($this->installedProtons->selectedIndex == -1)
            return;
            
        $this->appModule()->games->set('proton',$e->sender->selectedItem,$this->gameName->text);
    }

    /**
     * @event winetricksButton.action 
     */
    function doWinetricksButtonAction(UXEvent $e = null)
    {    
        $proton = filesWorker::getProtonExecutable($this->gameName->text);
        $prefixDir = fs::parent($this->appModule()->games->get('executable',$this->gameName->text)).'/OFME Prefix';
        if ($proton == false)
        {
            $this->toast(Localization::getByCode('FILESWORKER.PROTON.NOTFOUND'));
            return;
        }
        if (fs::isFile('/usr/bin/winetricks') == false)
        {
            $this->toast(Localization::getByCode('GAMESETTINGS.WINETRICKS.NOTFOUND'));
            return;
        }
        if (fs::isDir($prefixDir) == false)
        {
            $this->toast(Localization::getByCode('GAMESETTINGS.WINETRICKS.NOPREFIX'));
            return;
        }
        
        new Process(['winetricks'],null,['WINE'=>fs::parent($proton).'/files/bin/wine','WINEPREFIX'=>$prefixDir])->start();
    }






    /**
     * @event steamAppID.keyUp-Enter 
     */
    function doSteamAppIDKeyUpEnter(UXKeyEvent $e = null)
    {    
        if ($e->sender->text == null)
        {
            $this->toast(Localization::getByCode('BANNEREDITOR.STEAM.NOAPPID'));
            return;
        }
        
        try 
        {
            $banner = UXImage::ofUrl(FixParser::parseBanner($e->sender->text));
            $this->toast(Localization::getByCode('BANNEREDITOR.SUCCESS'));
            
            $this->setBanner($banner);
            
        } catch (Throwable $ex){$this->toast(Localization::getByCode('MAINFORM.STEAM.FAILED'));}
    }

    /**
     * @event bannerFileChooser.construct 
     */
    function doBannerFileChooserConstruct(UXEvent $e = null)
    {
        $view = new UXImageArea(new UXImage('res://.data/img/openIn.png'));
        $view->size = [20,20];
        
        quUI::generateSetButton($e->sender,Localization::getByCode('BANNEREDITOR.FILE.CHOOSE'),$view);
    }

    /**
     * @event bannerFileChooser.action 
     */
    function doBannerFileChooserAction(UXEvent $e = null)
    {
        $fc = new UXFileChooser;
        
        $fc->extensionFilters = [['extensions'=>['*.jpg','*.png'],'description'=>Localization::getByCode('FILECHOOSER.IMG.DESC')]];
        $fc->title = Localization::getByCode('FILECHOOSER.IMG.TITLE');
        
        $banner = $fc->showOpenDialog($this);
        if ($banner == null)
            return;
        
        $banner = new UXImage($banner);
        $this->setBanner($banner);
    }

    /**
     * @event label14.construct 
     */
    function doLabel14Construct(UXEvent $e = null)
    {
        $e->sender->text = Localization::getByCode('BANNEREDITOR.FILE.HEADER');
    }

    /**
     * @event vbox7.click 
     */
    function doVbox7Click(UXMouseEvent $e = null)
    {
        $this->steamAppID->requestFocus();
    }

    /**
     * @event label5.construct 
     */
    function doLabel5Construct(UXEvent $e = null)
    {
        $e->sender->text = Localization::getByCode('BANNEREDITOR.STEAM.HEADER');
    }

    /**
     * @event bannerEditor.click 
     */
    function doBannerEditorClick(UXMouseEvent $e = null)
    {
        $this->switchPage($this->bannerEditorPage,$this->bannerEditor);
    }

    /**
     * @event label11.construct 
     */
    function doLabel11Construct(UXEvent $e = null)
    {    
        $e->sender->text = Localization::getByCode('GAMESETTINGS.PROTONS');
    }

    /**
     * @event label12.construct 
     */
    function doLabel12Construct(UXEvent $e = null)
    {    
        $e->sender->text = Localization::getByCode('GAMESETTINGS.PROTONS.HINT');
    }

    /**
     * @event label8.construct 
     */
    function doLabel8Construct(UXEvent $e = null)
    {    
        $e->sender->text = Localization::getByCode('GAMESETTINGS.PROTONS.INSTALLED');
    }

    /**
     * @event label9.construct 
     */
    function doLabel9Construct(UXEvent $e = null)
    {    
        $e->sender->text = Localization::getByCode('GAMESETTINGS.PROTONS.AVAILABLE');
    }

    /**
     * @event label10.construct 
     */
    function doLabel10Construct(UXEvent $e = null)
    {    
        $e->sender->text = Localization::getByCode('BANNEREDITOR.STEAM.HINT');
    }

    /**
     * @event labelAlt.construct 
     */
    function doLabelAltConstruct(UXEvent $e = null)
    {    
        $e->sender->text = Localization::getByCode('GAMESETTINGS.ENVS.ARGS.BEFORE');
    }

    /**
     * @event label7.construct 
     */
    function doLabel7Construct(UXEvent $e = null)
    {    
        $e->sender->text = Localization::getByCode('GAMESETTINGS.ENVS.ARGS.AFTER');
    }

    /**
     * @event label3.construct 
     */
    function doLabel3Construct(UXEvent $e = null)
    {    
        $e->sender->text = Localization::getByCode('GAMESETTINGS.ADDITIONALS');
    }































    function switchPage($page,$icon)
    {
        if ($this->data('currentPage') == null)
            $this->data('currentPage',$this->overviewPage);
        
        $this->data('currentPage')->hide();
        
        $this->data('currentPage',$page);
        $page->show();
        
        Animation::moveTo($this->rect,200,$icon->x - 4,$icon->y);
        AnimationFX::play('FadeIn',$page);
    }
    
    function addBeforeArg($arg)
    {
        if (str::contains($this->argsBefore->text,$arg))
            return;
            
        if ($this->argsBefore->text != null)
            $this->argsBefore->text .= " $arg";
        else 
            $this->argsBefore->text = $arg;
            
        $this->appModule()->games->set('argsBefore',$this->argsBefore->text,$this->gameName->text);
    }
    
    function removeBeforeArg($arg)
    {
        $strLength = str::length($this->argsBefore->text);
        $argLength = str::length($arg);
        
        if ($argLength == $strLength)
            $this->argsBefore->text = null;
        else 
        {
            $argPosition = str::pos($this->argsBefore->text,$arg);
            if ($argPosition == 0 or $argPosition + $argLength != $strLength)
                $this->argsBefore->text = str::replace($this->argsBefore->text,"$arg ",null);
            else
                $this->argsBefore->text = str::replace($this->argsBefore->text," $arg",null);
        }
        
        $this->appModule()->games->set('argsBefore',$this->argsBefore->text,$this->gameName->text);
    }
    
    function setBanner(UXImage $banner)
    {
        try 
        {
            app()->form('MainForm')->gameHeader->image = $banner;
            app()->form('MainForm')->gamePanel->data('opener')->children[1]->image = $banner;
            app()->form('MainForm')->background->image = $banner;
            
            $gameName = app()->form('MainForm')->gamePanel->data('gameName');
            $bannersPath = System::getProperty('user.home').'/.config/OFME-Linux/banners';
            $currentBanner = $this->appModule()->games->get('banner',$gameName);
            
            if ($currentBanner != null)
                fs::delete($currentBanner);
            else 
                fs::makeDir($bannersPath);
            
            $banner->save($bannersPath.'/'.$gameName.'.png');
            $this->appModule()->games->set('banner',$bannersPath.'/'.$gameName.'.png',$gameName);
        } catch (Throwable $ex){UXDialog::show(Localization::getByCode('BANNEREDITOR.FILE.FAILED'),'ERROR');}
    }
}
