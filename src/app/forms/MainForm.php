<?php
namespace app\forms;

use php\gui\animatefx\AnimationFX;
use php\gui\controlsfx\UXToggleSwitch;
use script\HotKeyScript;
use php\desktop\HotKeyManager;
use Throwable;
use std, gui, framework, app;


class MainForm extends AbstractForm
{

    /**
     * @event container.construct 
     */
    function doContainerConstruct(UXEvent $e = null)
    {
        $fPane = new UXFlowPane;
        $fPane->hgap = $fPane->vgap = $fPane->paddingTop = $fPane->paddingRight = 35;
        $fPane->paddingLeft = 35;
        
        $e->sender->content = $fPane;
    }


    /**
     * @event construct 
     */
    function doConstruct(UXEvent $e = null)
    {
        $this->appModule()->overlayEmulator->disabled = true;
        
        foreach ($this->appModule()->games->toArray() as $name => $params)
        {
            $this->addGame($name,$params['executable'],$params['overrides'],$params['banner'],$params['icon']);
        }
    }


    /**
     * @event noGamesHeader.construct 
     */
    function doNoGamesHeaderConstruct(UXEvent $e = null)
    {    
        $e->sender->text = Localization::getByCode('MAINFORM.HEADER');
    }


    /**
     * @event addGame.action 
     */
    function doAddGameAction(UXEvent $e = null)
    {
        if ($this->addGame->graphic == $this->addGame->data('loading'))
            return;
        
        $fc = new UXFileChooser;

        $fc->extensionFilters = [['extensions'=>['*.exe'],'description'=>Localization::getByCode('FILECHOOSER.EXE.DESC')]];
        $fc->title = Localization::getByCode('FILECHOOSER.EXE.TITLE');

        $exe = $fc->showOpenDialog($this);
        if ($exe == null)
            return;

        $appPath = $exe->getParent();

        if (str::contains($exe,'/bin/') or str::contains($exe,'/Binaries/') or str::contains($exe,'/binaries/'))
        {
            UXDialog::showAndWait(Localization::getByCode('MAINFORM.EXENOTINROOT'),'WARNING',$this);
            $dc = new UXDirectoryChooser;

            $dc->title = Localization::getByCode('DIRCHOOSER.GAMEROOT.TITLE');

            $appPath = $dc->showDialog($this);
            if ($appPath == null)
                return;
        }
        
        $appName = UXDialog::input(Localization::getByCode('MAINFORM.SETGAMENAME'),fs::nameNoExt($exe),$this);
        if ($appName == false)
            return;
        if ($this->appModule()->games->section($appName) != [])
        {
            UXDialog::show(Localization::getByCode('MAINFORM.GAMEEXISTS'),'ERROR',$this);
            return;
        }
        
        $this->switchGameButton('loading');
        
        new Thread(function () use ($appName,$appPath,$exe){
            $files = fs::scan($appPath,['excludeDirs'=>true,'namePattern'=>
            '(?i)^(emp|custom)\.dll$|^win.*\.dll$|^(online|steam).*\.(dll|ini)$|^eos.*\.dll$|^epicfix.*\.dll$|^(winmm|dlllist)\.txt$|FreeTP.Org.url']);
    
            $parsed = FixParser::parseDlls($files);
            if ($parsed['overrides'] == null)
            {
                uiLater(function ()
                {
                    UXDialog::show(Localization::getByCode('MAINFORM.NOFIX'),'ERROR',$this);
                    $this->switchGameButton('add');
                });
                return;
            }
    
            if ($parsed['realAppId'] != null)
            {
                try
                {
                    $url = FixParser::parseBanner($parsed['realAppId']);
                    $imagesDir = System::getProperty('user.home').'/.config/OFME-Linux/banners';
    
                    fs::makeDir($imagesDir);
                    fs::copy($url,$imagesDir.'/'.$appName.'.jpg');
    
                    $image = $imagesDir.'/'.$appName.'.jpg';
                    $this->appModule()->games->set('banner',$image,$appName);
                } catch (Throwable $ex) {}
                
                $this->appModule()->games->set('steamID',$parsed['realAppId'],$appName);
            }
            elseif (str::contains($parsed['overrides'],'steam') == false and str::contains($parsed['overrides'],'eos'))
                uiLater(function (){UXDialog::show(Localization::getByCode('MAINFORM.EOSFIX'),'WARNING',$this);});
                
            if ($parsed['isFreeTP'])
                uiLater(function (){UXDialog::show(Localization::getByCode('MAINFORM.FREETP'),'WARNING',$this);});
    
            try
            {
                $appIcon = FixParser::parseIcon($exe);
                if ($appIcon != null)
                {
                    $iconsDir = System::getProperty('user.home').'/.config/OFME-Linux/icons';
                    $iconPath = $iconsDir.'/'.$appName;
    
                    fs::makeDir($iconsDir);
                    fs::copy($appIcon,$iconPath);
    
                    $this->appModule()->games->set('icon',$iconPath,$appName);
                    
                    fs::clean('/tmp/OFME-icon');
                    fs::delete('/tmp/OFME-icon');
                }
            } catch (Throwable $ex)
            {
                uiLater(function () use ($ex){UXDialog::show(sprintf(Localization::getByCode('MAINFORM.ICONPARSERERROR'),$ex->getMessage()),'ERROR',$this);});
            }
            
            
            $this->appModule()->games->put(['overrides'=>$parsed['overrides'],
                                           'executable'=>$exe,
                                           'mainPath'=>$appPath,
                                           'proton'=>'GE-Proton Latest',
                                           'steamRuntime'=>filesWorker::findSteamRuntime() != false ? true : false,
                                           'steamOverlay'=>true],$appName);
    
    
            uiLater(function () use ($appName,$exe,$parsed,$image,$iconPath)
            {
                $this->addGame($appName,$exe,$parsed['overrides'],$image,$iconPath);
                
                $this->switchGameButton('add');
            });
        })->start();
    }

    /**
     * @event addGame.construct 
     */
    function doAddGameConstruct(UXEvent $e = null)
    {
        $loadingGraphic = new UXMaterialProgressIndicator;
        $loadingGraphic->size = [20,20];
        $e->sender->graphic = new UXImageArea(new UXImage('res://.data/img/add.png'));
        $e->sender->graphic->size = [20,20];
        
        $e->sender->data('add',$e->sender->graphic);
        $e->sender->data('loading',$loadingGraphic);
        
        $e->sender->text = Localization::getByCode('MAINFORM.ADDGAME');
    }








    /**
     * @event playButton.construct 
     */
    function doPlayButtonConstruct(UXEvent $e = null)
    {
        $e->sender->graphic = new UXImageArea(new UXImage('res://.data/img/play.png'));
        $stopGraphic = new UXImageArea(new UXImage('res://.data/img/stop.png'));
        $e->sender->graphic->size = $stopGraphic->size = [20,20];
        
        $e->sender->data('play',$e->sender->graphic);
        $e->sender->data('stop',$stopGraphic);
        
        $e->sender->text = Localization::getByCode('MAINFORM.PLAY');
    }




    /**
     * @event desktopIcon.construct 
     */
    function doDesktopIconConstruct(UXEvent $e = null)
    {    
        $e->sender->text = Localization::getByCode('MAINFORM.MENU.CREATEDESKTOP');
    }

    /**
     * @event menuIcon.construct 
     */
    function doMenuIconConstruct(UXEvent $e = null)
    {    
        $e->sender->text = Localization::getByCode('MAINFORM.MENU.CREATEAPPMENU');
    }

    /**
     * @event desktopIcon.click 
     */
    function doDesktopIconClick(UXMouseEvent $e = null)
    {
        $gameName = $this->gamePanel->data('gameName');
        $icon = $this->appModule()->games->get('icon',$gameName);
        $desktopPath = str::trim(execute('xdg-user-dir DESKTOP',true)->getInput()->readFully())."/$gameName.desktop";
        if (fs::isFile($desktopPath))
        {
            fs::delete($desktopPath);
            $this->desktopIcon->selected = false;
        }
        else 
        {
            file_put_contents($desktopPath,filesWorker::generateDesktopEntry($gameName,$icon));
            new Process(['chmod','+x',$desktopPath])->start();
            
            $this->desktopIcon->selected = true;
        }
    }

    /**
     * @event menuIcon.click 
     */
    function doMenuIconClick(UXMouseEvent $e = null)
    {
        $gameName = $this->gamePanel->data('gameName');
        $icon = $this->appModule()->games->get('icon',$gameName);
        $menuPath = System::getProperty('user.home')."/.local/share/applications/$gameName.desktop";
        if (fs::isFile($menuPath))
        {
            fs::delete($menuPath);
            $this->menuIcon->selected = false;
        }
        else 
        {
            file_put_contents($menuPath,filesWorker::generateDesktopEntry($this->gamePanel->data('gameName'),$icon));
            
            $this->menuIcon->selected = true;
        }
    }



    /**
     * @event playButton.action 
     */
    function doPlayButtonAction(UXEvent $e = null)
    {    
        if ($e->sender->graphic == $e->sender->data('stop'))
        {
            $exec = $this->appModule()->games->get('executable',$this->gamePanel->data('gameName'));
            $kill = new Process(['pkill','-f',fs::nameNoExt($exec)])->startAndWait();
            
            if ($kill->getExitValue() != 0)
            {
                $this->toast(Localization::getByCode('MAINFORM.KILLFAILED'));
                $this->data('manualKill',false);
            }
            else
            {
                $this->switchPlayButton('play');
            }
        }
        else 
        {
            $this->runGame($this->gamePanel->data('gameName'));
        }
    }



    /**
     * @event donate.action 
     */
    function doDonateAction(UXEvent $e = null)
    {
        execute('xdg-open https://www.donationalerts.com/r/queinu');
    }

    /**
     * @event donate.construct 
     */
    function doDonateConstruct(UXEvent $e = null)
    {
        $e->sender->text = Localization::getByCode('MAINFORM.DONATE');
        $e->sender->graphic = new UXImageArea(new UXImage('res://.data/img/donate.png'));
        $e->sender->graphic->size = [20,20];
    }

    /**
     * @event about.action 
     */
    function doAboutAction(UXEvent $e = null)
    {
        app()->showForm('about');
    }

    /**
     * @event about.construct 
     */
    function doAboutConstruct(UXEvent $e = null)
    {
        $e->sender->text = Localization::getByCode('MAINFORM.ABOUT');
        $e->sender->graphic = new UXImageArea(new UXImage('res://.data/img/about.png'));
        $e->sender->graphic->size = [20,20];
    }

    /**
     * @event timeHeader.construct 
     */
    function doTimeHeaderConstruct(UXEvent $e = null)
    {    
        $e->sender->text = Localization::getByCode('MAINFORM.MENU.TIMESTEMP.HEADER');
    }

    /**
     * @event gameDebugButton.construct 
     */
    function doGameDebugButtonConstruct(UXEvent $e = null)
    {
        $e->sender->text = Localization::getByCode('MAINFORM.MENU.RUNDEBUG');
        $e->sender->tooltipText = Localization::getByCode('MAINFORM.MENU.RUNDEBUG.TOOLTIP');
        $e->sender->graphic = new UXImageArea(new UXImage('res://.data/img/debug.png'));
        $e->sender->graphic->size = [15,15];
    }

    /**
     * @event gameDebugButton.action 
     */
    function doGameDebugButtonAction(UXEvent $e = null)
    {
        $this->runGame($this->gamePanel->data('gameName'),true);
    }

    /**
     * @event gameSettingsButton.construct 
     */
    function doGameSettingsButtonConstruct(UXEvent $e = null)
    {
        $e->sender->text = Localization::getByCode('MAINFORM.MENU.SETTINGS');
        $e->sender->graphic = new UXImageArea(new UXImage('res://.data/img/settings.png'));
        $e->sender->graphic->size = [15,15];
    }

    /**
     * @event gameSettingsButton.action 
     */
    function doGameSettingsButtonAction(UXEvent $e = null)
    {
        if (app()->form('gameSettings')->gameName->graphic != null and app()->form('gameSettings')->gameName->graphic->isFree() == false)
            app()->form('gameSettings')->gameName->graphic->free();
        
        app()->form('gameSettings')->data('gameName',$this->gamePanel->data('gameName'));
        app()->form('gameSettings')->title = $this->gamePanel->data('gameName');
        app()->form('gameSettings')->gameIcon->image = $this->gamePanel->data('opener')->children[3]->children[0]->graphic->image;
        
        app()->showForm('gameSettings');
    }

    /**
     * @event gameDeleteButton.construct 
     */
    function doGameDeleteButtonConstruct(UXEvent $e = null)
    {
        $e->sender->text = Localization::getByCode('MAINFORM.MENU.DELETE');
        $e->sender->graphic = new UXImageArea(new UXImage('res://.data/img/remove.png'));
        $e->sender->graphic->size = [15,15];
    }

    /**
     * @event gameDeleteButton.action 
     */
    function doGameDeleteButtonAction(UXEvent $e = null)
    {
        app()->showForm('gameRemover');
    }

    /**
     * @event winetricksButton.construct 
     */
    function doWinetricksButtonConstruct(UXEvent $e = null)
    {
        $e->sender->graphic = new UXImageArea(new UXImage('res://.data/img/wine.png'));
        $e->sender->graphic->size = [15,15];
    }

    /**
     * @event winetricksButton.action 
     */
    function doWinetricksButtonAction(UXEvent $e = null)
    {
        $proton = filesWorker::getProtonExecutable($this->gamePanel->data('gameName'));
        $prefixDir = fs::parent($this->appModule()->games->get('executable',$this->gamePanel->data('gameName'))).'/OFME Prefix';
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
     * @event protonDBButton.construct 
     */
    function doProtonDBButtonConstruct(UXEvent $e = null)
    {
        $e->sender->graphic = new UXImageArea(new UXImage('res://.data/img/protondb.png'));
        $e->sender->graphic->size = [15,15];
    }

    /**
     * @event protonDBButton.action 
     */
    function doProtonDBButtonAction(UXEvent $e = null)
    {
        execute('xdg-open https://protondb.com/app/'.$this->appModule()->games->get('steamID',$this->gamePanel->data('gameName')));
    }

    /**
     * @event steamButton.construct 
     */
    function doSteamButtonConstruct(UXEvent $e = null)
    {
        $e->sender->graphic = new UXImageArea(new UXImage('res://.data/img/steam.png'));
        $e->sender->graphic->size = [15,15];
    }

    /**
     * @event steamButton.action 
     */
    function doSteamButtonAction(UXEvent $e = null)
    {
        execute('xdg-open https://store.steampowered.com/app/'.$this->appModule()->games->get('steamID',$this->gamePanel->data('gameName')));
    }

    /**
     * @event steamDBButton.construct 
     */
    function doSteamDBButtonConstruct(UXEvent $e = null)
    {
        $e->sender->graphic = new UXImageArea(new UXImage('res://.data/img/db.png'));
        $e->sender->graphic->size = [15,15];
    }

    /**
     * @event steamDBButton.action 
     */
    function doSteamDBButtonAction(UXEvent $e = null)
    {
        execute('xdg-open https://steamdb.info/app/'.$this->appModule()->games->get('steamID',$this->gamePanel->data('gameName')));
    }

    /**
     * @event gameFolderButton.construct 
     */
    function doGameFolderButtonConstruct(UXEvent $e = null)
    {
        $e->sender->text = Localization::getByCode('GAMESETTINGS.FOLDERS.BUTTON');
        $e->sender->graphic = new UXImageArea(new UXImage('res://.data/img/folder.png'));
        $e->sender->graphic->size = [15,15];
        
        $menu = new UXContextMenu;
        $gameFolder = new UXMenuItem(Localization::getByCode('GAMESETTINGS.FOLDERS.GAME'));
        $prefixFolder = new UXMenuItem(Localization::getByCode('GAMESETTINGS.FOLDERS.PREFIX'));
        
        $gameFolder->on('action',function ()
        {
            open($this->appModule()->games->get('mainPath',$this->gamePanel->data('gameName')) ?? fs::parent($this->appModule()->games->get('executable',$this->gamePanel->data('gameName'))));
        });
        $prefixFolder->on('action',function ()
        {
            $prefixDir = fs::parent($this->appModule()->games->get('executable',$this->gamePanel->data('gameName'))).'/OFME Prefix/pfx/drive_c';
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
     * @event runInPrefixButton.construct 
     */
    function doRunInPrefixButtonConstruct(UXEvent $e = null)
    {
        $e->sender->text = Localization::getByCode('MAINFORM.MENU.RUN');
        $e->sender->tooltipText = Localization::getByCode('MAINFORM.MENU.RUN.TOOLTIP');
        $e->sender->graphic = new UXImageArea(new UXImage('res://.data/img/run.png'));
        $e->sender->graphic->size = [15,15];
    }

    /**
     * @event runInPrefixButton.action 
     */
    function doRunInPrefixButtonAction(UXEvent $e = null)
    {    
        $proton = filesWorker::getProtonExecutable($this->gamePanel->data('gameName'));
        $prefixDir = fs::parent($this->appModule()->games->get('executable',$this->gamePanel->data('gameName'))).'/OFME Prefix';
        if ($proton == false)
        {
            $this->toast(Localization::getByCode('FILESWORKER.PROTON.NOTFOUND'));
            return;
        }
        
        $fc = new UXFileChooser;

        $fc->extensionFilters = [['extensions'=>['*.exe'],'description'=>Localization::getByCode('FILECHOOSER.EXE.DESC')]];
        $fc->title = Localization::getByCode('FILECHOOSER.EXE.TITLE');

        $exe = $fc->showOpenDialog($this);
        if ($exe == null)
            return;
            
        new Process([$proton,'run',$exe],fs::parent($exe),['STEAM_COMPAT_DATA_PATH'=>$prefixDir,
                                                           'STEAM_COMPAT_CLIENT_INSTALL_PATH'=>System::getProperty('user.home').'/.steam/steam'])->start();
    }
















    
    function addGame($gameName,$exec,$overrides,$image = null,$icon = null)
    {
        $gamePanel = $this->instance('prototypes.panel');
        
        $iconView = new UXImageArea(new UXImage(fs::isFile($icon) ? $icon : 'res://.data/img/noImage.png'));
        $iconView->size = [34,34];
        $iconView->proportional = $iconView->centered = $iconView->stretch = true;
        
        $clip = new UXRectangle;
        $clip->size = $gamePanel->children[1]->size;
        $clip->arcHeight = $clip->arcWidth = $gamePanel->borderRadius * 2;
        
        $scaleAnim = new ScaleAnimationBehaviour;
        $scaleAnim->duration = 400;
        $scaleAnim->scale = 1.05;
        $scaleAnim->when = 'HOVER';
        $scaleAnim->apply($gamePanel);
        
        $shadow = new DropShadowEffectBehaviour;
        $shadow->color = '#0000004d';
        $shadow->apply($gamePanel);
                
        $gamePanel->children[3]->children[0]->text = $gameName;
        $gamePanel->children[1]->image = new UXImage(fs::isFile($image) ? $image : 'res://.data/img/noBanner.png');
        $gamePanel->children[1]->clip = $clip;
        $gamePanel->children[3]->children[0]->graphic = $iconView;
        $gamePanel->on('click',function (UXMouseEvent $e) use ($gameName)
        {
            $this->showGameMenu($gameName,$e->sender->children[1]->image,$e->sender);
        });
        $gamePanel->on('mouseEnter',function ($e) use ($gamePanel)
        {
            $this->background->image = $gamePanel->children[1]->image;
            quUI::animateWithoutConflict('FadeIn',$this->background,1.3);
        });
        $gamePanel->on('mouseExit',function ($e) use ($gamePanel)
        {
            if ($this->container->enabled)
                quUI::animateWithoutConflict('FadeOut',$this->background,1.3);
        });
        
        
        $this->container->content->children->add($gamePanel);
        
        if ($this->noGamesHeader->visible)
        {
            $this->noGamesHeader->visible = false;
        }
    }
    
    function showGameMenu($name,$header,$sender)
    {
        $this->gamePanel->show();
        
        $this->desktopIcon->selected = fs::isFile(str::trim(execute('xdg-user-dir DESKTOP',true)->getInput()->readFully())."/$name.desktop");
        $this->menuIcon->selected = fs::isFile(System::getProperty('user.home')."/.local/share/applications/$name.desktop");
        
        $this->on('mouseDown',function (UXMouseEvent $e)
        {
            if ($e->x < $this->gamePanel->x)
            {
                $this->off('mouseDown');
                
                $this->hideGameMenu();
            }
        });
        
        $this->container->enabled = false;
        $this->addGame->hide();
        
        $this->gameHeader->image = $header;
        $this->updateTimeSpent($name);
        $this->gamePanel->data('gameName',$name); 
        $this->gamePanel->data('opener',$sender);  
        $this->protonDBButton->enabled = $this->steamButton->enabled = $this->steamDBButton->enabled = $this->appModule()->games->get('steamID',$name) != null;
        if (new Process(['pgrep','-af',fs::nameNoExt($this->appModule()->games->get('executable',$name))])->startAndWait()->getExitValue() == 1)
            $this->switchPlayButton('play');
        else 
            $this->switchPlayButton('stop');
        
        Animation::fadeTo($this->buttonBox,400,0.5);
        quUI::animateWithoutConflict('FadeInRight',$this->gamePanel,1.4);
    }
    
    function hideGameMenu()
    {
        $this->addGame->show();
        $this->container->enabled = true;
        
        Animation::fadeIn($this->buttonBox,400);
        quUI::animateWithoutConflict('FadeOutRight',$this->gamePanel,1.4,function (){$this->gamePanel->hide();});
        quUI::animateWithoutConflict('FadeOut',$this->background,1.4);
    }
    
    function runGame($gameName,$debug = false)
    {
        $process = filesWorker::generateProcess($gameName,$debug);
        if ($process == null)
            return;
        
        $this->appModule()->overlayEmulator->disabled = $this->appModule()->games->get('steamOverlay',$gameName);
        $this->switchPlayButton('stop');
        
        new Thread(function () use ($process,$gameName,$debug)
        {
            filesWorker::run($process,$gameName,$debug);
            
            $this->appModule()->overlayEmulator->disabled = true;
            if ($this->gamePanel->data('gameName') == $gameName)
            {
                uiLater(function () use ($gameName)
                {
                    if ($this->gamePanel->data('gameName') == $gameName)
                        $this->switchPlayButton('play');
                        
                    $this->updateTimeSpent($gameName);
                });
            }
        })->start();
    }
    
    function switchGameButton($status)
    {
        if ($status == 'add')
        {
            $this->addGame->graphic = $this->addGame->data('add');
            $this->addGame->text = Localization::getByCode('MAINFORM.ADDGAME');
        }
        else 
        {
            $this->addGame->graphic = $this->addGame->data('loading');
            $this->addGame->text = Localization::getByCode('MAINFORM.LOADINGGAME');
        }
    }
    
    function switchPlayButton($status)
    {
        if ($status == 'stop')
        {
            $this->playButton->text = Localization::getByCode('MAINFORM.STOP');
            $this->playButton->graphic = $this->playButton->data('stop');
            $this->gameDebugButton->enabled = false;
        }
        else 
        {
            $this->playButton->text = Localization::getByCode('MAINFORM.PLAY');
            $this->playButton->graphic = $this->playButton->data('play');
            $this->gameDebugButton->enabled = true;
        }
    }
    
    function updateTimeSpent($gameName)
    {
        $timeSpent = $this->appModule()->games->get('timeSpent',$gameName);
        if ($timeSpent < 3600)
        {
            $minutes = round($timeSpent / 60);
            $this->timeLabel->text = sprintf(Localization::getByCode('MAINFORM.TIMESPENT.MINUTES'),$minutes >= 1 ? $minutes : 0);
        }
        else 
        {
            $this->timeLabel->text = sprintf(Localization::getByCode('MAINFORM.TIMESPENT.HOURS'),round($timeSpent / 3600));
        }
    }
}
