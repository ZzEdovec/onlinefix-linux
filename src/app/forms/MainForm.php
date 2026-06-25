<?php
namespace app\forms;

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
        $fPane->hgap = $fPane->vgap = $fPane->paddingTop = $fPane->paddingRight = $fPane->paddingLeft = $fPane->paddingBottom = 35;
        
        $e->sender->content = $fPane;
    }


    /**
     * @event construct 
     */
    function doConstruct(UXEvent $e = null)
    {
        if ($this->appModule()->launcher->get('fullscreen','User Settings'))
            $this->fullScreen = true;
            
        foreach ($this->appModule()->games->toArray() as $name => $params)
            $this->addGame($name,$params['executable'],$params['overrides'],$params['banner'],$params['icon']);
        
        $this->requestFocus();
    }

    /**
     * @event noGamesHeader.construct 
     */
    function doNoGamesHeaderConstruct(UXEvent $e = null)
    {    
        $e->sender->text = $this->_('MAINFORM.HEADER');
    }

    /**
     * @event playButton.construct 
     */
    function doPlayButtonConstruct(UXEvent $e = null)
    {
        $e->sender->graphic = new UXImageArea(new UXImage('res://.data/img/play.png'));
        $stopGraphic = new UXImageArea(new UXImage('res://.data/img/stop.png'));
        $waitGraphic = new UXImageArea(new UXImage('res://.data/img/wait.png'));
        $e->sender->graphic->size = $stopGraphic->size = $waitGraphic->size = [20,20];
        
        $e->sender->data('play',$e->sender->graphic);
        $e->sender->data('stop',$stopGraphic);
        $e->sender->data('wait',$waitGraphic);
        
        $e->sender->text = $this->_('MAINFORM.PLAY');
    }
    
    /**
     * @event desktopIcon.construct 
     */
    function doDesktopIconConstruct(UXEvent $e = null)
    {    
        $e->sender->text = $this->_('MAINFORM.MENU.CREATEDESKTOP');
    }

    /**
     * @event menuIcon.construct 
     */
    function doMenuIconConstruct(UXEvent $e = null)
    {    
        $e->sender->text = $this->_('MAINFORM.MENU.CREATEAPPMENU');
    }

    /**
     * @event desktopIcon.click 
     */
    function doDesktopIconClick(UXMouseEvent $e = null)
    {
        $this->desktopIcon->selected = $this->createDesktopFile(str::trim(execute('xdg-user-dir DESKTOP',true)->getInput()->readFully()));
    }

    /**
     * @event menuIcon.click 
     */
    function doMenuIconClick(UXMouseEvent $e = null)
    {
        $this->menuIcon->selected = $this->createDesktopFile(System::getProperty('user.home')."/.local/share/applications");
    }

    function createDesktopFile($path) {
        $gameName = $this->gamePanel->data('gameName');
        $desktopPath = "$path/$gameName.desktop";
        
        if (fs::isFile($desktopPath)) {
            fs::delete($desktopPath);
            return false;
        }
        else {
            $icon = $this->appModule()->games->get('icon', $gameName);
            
            file_put_contents($desktopPath, FilesWorker::generateDesktopEntry($gameName, $icon));
            new Process(['chmod','+x',$desktopPath])->start();
            
            return true;
        }
    }

    /**
     * @event playButton.action 
     */
    function doPlayButtonAction(UXEvent $e = null)
    {    
        if ($e->sender->graphic == $e->sender->data('stop'))
        {
            $wineserver = FilesWorker::getProtonExecutable($this->gamePanel->data('gameName'),'wineserver',true);
            $prefixDir = FilesWorker::getProtonPrefixPath($this->gamePanel->data('gameName'),'wine');
                         
            try{$kill = new Process([$wineserver,'-k'],null,['WINEPREFIX'=>$prefixDir])->startAndWait();}
            catch (Throwable $ex){$kill = new Process(['pkill','-f',fs::name($this->appModule()->games->get('executable',$this->gamePanel->data('gameName')))])->startAndWait();}
            
            if ($kill->getExitValue() != 0)
                $this->toast($this->_('MAINFORM.KILLFAILED'));
            else
                $this->switchPlayButton('play');
        }
        else 
            $this->runGame($this->gamePanel->data('gameName'));
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
        $e->sender->text = $this->_('MAINFORM.DONATE');
        $e->sender->graphic = new UXImageArea(new UXImage('res://.data/img/donate.png'));
        $e->sender->graphic->size = [20,20];
    }

    /**
     * @event about.action 
     */
    function doAboutAction(UXEvent $e = null)
    {
        quUI::showFormAndFocus('launcherSettings');
    }

    /**
     * @event about.construct 
     */
    function doAboutConstruct(UXEvent $e = null)
    {
        $e->sender->text = $this->_('MAINFORM.SETTINGS');
        $e->sender->graphic = new UXImageArea(new UXImage('res://.data/img/settings-hires.png'));
        $e->sender->graphic->size = [20,20];
    }

    /**
     * @event timeHeader.construct 
     */
    function doTimeHeaderConstruct(UXEvent $e = null)
    {    
        $e->sender->text = $this->_('MAINFORM.MENU.TIMESTEMP.HEADER');
    }

    /**
     * @event gameDebugButton.construct 
     */
    function doGameDebugButtonConstruct(UXEvent $e = null)
    {
        $e->sender->text = $this->_('MAINFORM.MENU.RUNDEBUG');
        $e->sender->tooltipText = $this->_('MAINFORM.MENU.RUNDEBUG.TOOLTIP');
        $e->sender->graphic = new UXImageArea(new UXImage('res://.data/img/debug.png'));
        $e->sender->graphic->size = [15,15];
    }

    /**
     * @event gameDebugButton.action 
     */
    function doGameDebugButtonAction(UXEvent $e = null)
    {
        $debug = UXDialog::input('WINEDEBUG:','+err,+warn,+seh');
        if ($debug == null)
            return;
            
        $this->runGame($this->gamePanel->data('gameName'),$debug);
    }

    /**
     * @event gameSettingsButton.construct 
     */
    function doGameSettingsButtonConstruct(UXEvent $e = null)
    {
        $e->sender->text = $this->_('MAINFORM.MENU.SETTINGS');
        $e->sender->graphic = new UXImageArea(new UXImage('res://.data/img/settings.png'));
        $e->sender->graphic->size = [15,15];
    }

    /**
     * @event gameSettingsButton.action 
     */
    function doGameSettingsButtonAction(UXEvent $e = null)
    {
        $form = app()->getNewForm('gameSettings');
        
        $form->data('gameName',$this->gamePanel->data('gameName'));
        $form->title = $this->gamePanel->data('gameName');
        $form->gameIcon->image = $this->gamePanel->data('opener')->children[3]->children[0]->graphic->image;
        $form->banner->image = $this->gamePanel->data('opener')->children[1]->image;
        
        $form->show();
    }

    /**
     * @event gameDeleteButton.construct 
     */
    function doGameDeleteButtonConstruct(UXEvent $e = null)
    {
        $e->sender->text = $this->_('REMOVE');
        $e->sender->graphic = new UXImageArea(new UXImage('res://.data/img/remove.png'));
        $e->sender->graphic->size = [15,15];
    }

    /**
     * @event gameDeleteButton.action 
     */
    function doGameDeleteButtonAction(UXEvent $e = null)
    {
        quUI::showFormAndFocus('gameRemover');
    }

    /**
     * @event utilitiesButton.construct 
     */
    function doUtilitiesButtonConstruct(UXEvent $e = null)
    {
        $e->sender->text = $this->_('MAINFORM.MENU.UTILITIES');
        $e->sender->graphic = new UXImageArea(new UXImage('res://.data/img/wine.png'));
        $e->sender->graphic->size = [15,15];
        
        $runner = function ($util) use ($e)
        {
            $wine = FilesWorker::getProtonExecutable($this->gamePanel->data('gameName'),'wine');
            $prefix = FilesWorker::getProtonPrefixPath($this->gamePanel->data('gameName'),'wine');
            if ($wine == false)
            {
                $this->toast($this->_('FILESWORKER.PROTON.NOTFOUND'));
                return;
            }
            
            $e->sender->enabled = false;
            new Thread(function () use ($wine,$prefix,$e,$util)
            {
                switch ($util)
                {
                    case ('winetricks'):
                        $process = new Process(['winetricks'],null,['WINE'=>$wine,'WINEPREFIX'=>$prefix])->start();
                    break;
                    case ('wineconsole'):
                        $process = new Process([$wine,'start','cmd.exe'],null,['WINEPREFIX'=>$prefix])->start();
                    break;
                    default: 
                        $process = new Process([$wine,"$prefix/drive_c/windows/syswow64/$util"],null,['WINEPREFIX'=>$prefix])->start();
                    break;
                }
                
                FilesWorker::hookProcessOuts($process);
                uiLater(function () use ($e){$e->sender->enabled = true;});
            })->start();
        };
        
        $menu = new UXContextMenu;
        $winecfg = new UXMenuItem($this->_('MAINFORM.UTILITIES.WINECFG'));
        $taskmgr = new UXMenuItem($this->_('MAINFORM.UTILITIES.TASKMGR'));
        $control = new UXMenuItem($this->_('MAINFORM.UTILITIES.CONTROL'));
        $explorer = new UXMenuItem($this->_('MAINFORM.UTILITIES.EXPLORER'));
        $cmd = new UXMenuItem($this->_('MAINFORM.UTILITIES.CMD'));
        $regedit = new UXMenuItem($this->_('MAINFORM.UTILITIES.REGEDIT'));
        $winetricks = new UXMenuItem('Winetricks');
        
        $winecfg->on('action',function () use ($runner){$runner('winecfg.exe');});
        $taskmgr->on('action',function () use ($runner){$runner('taskmgr.exe');});
        $control->on('action',function () use ($runner){$runner('control.exe');});
        $explorer->on('action',function () use ($runner){$runner('explorer.exe');});
        $cmd->on('action',function () use ($runner){$runner('wineconsole');});
        $regedit->on('action',function () use ($runner){$runner('regedit.exe');});
        $winetricks->on('action',function () use ($runner){$runner('winetricks');});
        
        if (fs::isFile('/usr/bin/winetricks') == false)
            $winetricks->enabled = false;
        
        $menu->items->addAll([$winecfg,$taskmgr,$control,$explorer,$cmd,$regedit,UXMenuItem::createSeparator(),$winetricks]);
        $e->sender->data('menu',$menu);
    }

    /**
     * @event utilitiesButton.click 
     */
    function doUtilitiesButtonClick(UXMouseEvent $e = null)
    {
        $e->sender->data('menu')->showByNode($e->sender,$e->x,$e->y);
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
        $e->sender->text = $this->_('GAMESETTINGS.FOLDERS.BUTTON');
        $e->sender->graphic = new UXImageArea(new UXImage('res://.data/img/folder.png'));
        $e->sender->graphic->size = [15,15];
        
        $menu = new UXContextMenu;
        $gameFolder = new UXMenuItem($this->_('GAMESETTINGS.FOLDERS.GAME'));
        $prefixFolder = new UXMenuItem($this->_('GAMESETTINGS.FOLDERS.PREFIX'));
        
        $gameFolder->on('action',function ()
        {
            open($this->appModule()->games->get('mainPath',$this->gamePanel->data('gameName')) ?? 
                 fs::parent($this->appModule()->games->get('executable',$this->gamePanel->data('gameName'))));
        });
        $prefixFolder->on('action',function ()
        {
            $prefixDir = FilesWorker::getProtonPrefixPath($this->gamePanel->data('gameName'));
            if (fs::isDir($prefixDir))
                open($prefixDir);
            else 
                $this->toast($this->_('GAMESETTINGS.WINETRICKS.NOPREFIX'));
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
        $e->sender->text = $this->_('MAINFORM.MENU.RUN');
        $e->sender->tooltipText = $this->_('MAINFORM.MENU.RUN.TOOLTIP');
        $e->sender->graphic = new UXImageArea(new UXImage('res://.data/img/run.png'));
        $e->sender->graphic->size = [15,15];
    }

    /**
     * @event runInPrefixButton.action 
     */
    function doRunInPrefixButtonAction(UXEvent $e = null)
    {    
        $proton = FilesWorker::getProtonExecutable($this->gamePanel->data('gameName'));
        $prefixDir = FilesWorker::getProtonPrefixPath($this->gamePanel->data('gameName'));
        if ($proton == false)
        {
            $this->toast($this->_('FILESWORKER.PROTON.NOTFOUND'));
            return;
        }
        
        $fc = new UXFileChooser;

        $fc->extensionFilters = [['extensions'=>['*.exe'],'description'=>$this->_('FILECHOOSER.EXE.DESC')]];
        $fc->title = $this->_('FILECHOOSER.EXE.TITLE');

        $exe = $fc->showOpenDialog($this);
        if ($exe == null)
            return;
            
        $process = new Process([$proton,'run',$exe],fs::parent($exe),
        ['STEAM_COMPAT_DATA_PATH'=>$prefixDir,'STEAM_COMPAT_CLIENT_INSTALL_PATH'=>System::getProperty('user.home').'/.steam/steam'])->start();
                                                           
        FilesWorker::hookProcessOuts($process,false,false);
    }

    /**
     * @event background.construct 
     */
    function doBackgroundConstruct(UXEvent $e = null)
    {    
        if (System::getProperty('prism.forceGPU') == false)
            $e->sender->free();
    }

    /**
     * @event addGame.click 
     */
    function doAddGameClick(UXMouseEvent $e = null)
    {
        if ($this->addGame->graphic == $this->addGame->data('loading') and $this->addGame->graphic != null)
            return;
            
        $dc = new UXDirectoryChooser;
        $dc->title = $this->_('ADDGAME.FILECHOOSER');

        $path = $dc->showDialog($this->visible ? $this : app()->form('MainForm'));
        if ($path == null)
            return;
        
        try{$this->switchGameButton('loading');} catch (Throwable $ex){}
        new Thread(function () use ($path)
        {
            $files = self::scanDir($path);
                                     
            uiLater(function () use ($files,$path)
            {
                try {$this->switchGameButton('add','addGame'); } catch (Throwable $ex){}
                
                $form = quUI::showFormAndFocus('newGameConfigurator', true);
                uiLater(function () use ($files,$path,$form) { $form->prepareForGame($files,$path); });
            });
        })->start();
    }
    
    function switchGameButton($status)
    {
        if ($status == 'add')
        {
            $this->addGame->graphic = $this->addGame->data('add');
            $this->addGame->text = $this->_('MAINFORM.ADDGAME');
        }
        else 
        {
            $this->addGame->graphic = $this->addGame->data('loading');
            $this->addGame->text = $this->_('MAINFORM.LOADINGGAME');
        }
    }
    
    static function scanDir($path)
    {
        $files = fs::scan(
            $path,
            ['callback'=> function (File $f) {
                 if ($f->isDirectory() or 
                      Regex::match('\.(exe|vbs|bat|rar)$',$f) == false)
                      return false;
                 
                 return $f;
             }]
         );
            
        return $files;
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
        
        $e->sender->text = $this->_('MAINFORM.ADDGAME');
    }

    /**
     * @event keyUp-Esc 
     */
    function doKeyUpEsc(UXKeyEvent $e = null)
    {    
        if ($this->gamePanel->visible)
            $this->hideGameMenu();
    }

    /**
     * @event close 
     */
    function doClose(UXWindowEvent $e = null)
    {    
        fs::delete('/tmp/ofllpid');
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
        MainForm::addBasicEffects($gamePanel);
        
        $gamePanel->children[3]->children[0]->text = $gameName;
        $gamePanel->children[1]->image = new UXImage(fs::isFile($image) ? $image : 'res://.data/img/noBanner.png');
        $gamePanel->children[1]->clip = $clip;
        $gamePanel->children[3]->children[0]->graphic = $iconView;
        $gamePanel->on('click',function (UXMouseEvent $e) use ($gameName)
        {
            $this->showGameMenu($gameName,$e->sender->children[1]->image,$e->sender);
        });
        
        $this->container->content->children->add($gamePanel);
        
        if ($this->noGamesHeader->visible)
            $this->noGamesHeader->visible = false;
    }
    
    function addStubGame()
    {
        if ($this->noGamesHeader->visible)
            $this->noGamesHeader->visible = false;
            
        $box = $this->instance('prototypes.gameStubBox');
        MainForm::addBasicEffects($box);
        $this->container->content->children->add($box);
        
        $childrens = $box->children->toArray();
        return ['box'=>$box,'gameName'=>$childrens[0],'status'=>$childrens[2]];
    }
    
    function removeStubGame($box)
    {
        $box->free();
        
        if ($this->container->content->children->isEmpty())
            $this->noGamesHeader->visible = true;
    }
    
    function showGameMenu($name,$header,$sender)
    {
        $this->gamePanel->show();
        
        $this->desktopIcon->selected = fs::isFile(str::trim(execute('xdg-user-dir DESKTOP',true)->getInput()->readFully())."/$name.desktop");
        $this->menuIcon->selected = fs::isFile(System::getProperty('user.home')."/.local/share/applications/$name.desktop");
        
        $this->on('mouseDown',function (UXMouseEvent $e)
        {
            if ($e->x < $this->gamePanel->x)
                $this->doKeyUpEsc();
        });
        
        $this->addGame->hide();
        
        $this->gameHeader->image = $header;
        $this->gamePanel->data('gameName',$name); 
        $this->gamePanel->data('opener',$sender);  
        $this->protonDBButton->enabled = $this->steamButton->enabled = $this->steamDBButton->enabled = $this->appModule()->games->get('steamID',$name) != null;
        
        $this->updateTimeSpent($name);
        
        $this->switchPlayButton('stop');
        new Thread(function () use ($name){$this->waitForWineServerTerminate($name);})->start();
        
        if (System::getProperty('prism.forceGPU'))
        {
            $this->background->image = $sender->children[1]->image;
            quUI::animateWithoutConflict('FadeIn',$this->background,1.4);
        }
           
        Animation::fadeTo($this->buttonsBox,450,0.5,function (){$this->buttonsBox->enabled = false;});
        Animation::fadeTo($this->container,450,0.5,function (){$this->container->enabled = false;});
        quUI::animateWithoutConflict('FadeInRight',$this->gamePanel,1.4);
    }
    
    function hideGameMenu()
    {
        if ($this->playButton->graphic == $this->playButton->data('wait'))
            return;
            
        $this->off('mouseDown');
        $this->addGame->show();
        $this->container->enabled = true;
        
        Animation::fadeIn($this->buttonsBox,450,function (){$this->buttonsBox->enabled = true;});
        Animation::fadeIn($this->container,450,function (){$this->container->enabled = true;});
        quUI::animateWithoutConflict('FadeOutRight',$this->gamePanel,1.4,function (){$this->gamePanel->hide();});
        if (System::getProperty('prism.forceGPU')) {quUI::animateWithoutConflict('FadeOut',$this->background,1.4);}
    }
    
    static function addBasicEffects($object)
    {
        $scaleAnim = new ScaleAnimationBehaviour;
        $scaleAnim->duration = 300;
        $scaleAnim->scale = 1.05;
        $scaleAnim->when = 'HOVER';
        $scaleAnim->apply($object);
        
        $shadow = new DropShadowEffectBehaviour;
        $shadow->color = '#0000004d';
        $shadow->apply($object);
    }
    
    function runGame($gameName,$debug = false)
    {
        new Thread(function () use ($process,$gameName,$debug)
        {
            $process = FilesWorker::generateProcess($gameName,$debug);
            if ($process == null)
                return;
            
            uiLater(function (){$this->switchPlayButton('stop');});
            
            FilesWorker::run($process,$gameName,$debug);
            
            Logger::info("$gameName process has finished, waiting for the wineprefix to complete");
            $this->waitForWineServerTerminate($gameName);
            Logger::info("$gameName fully terminated");
        })->start();
    }
    
    function waitForWineServerTerminate($gameName)
    {
        $protonExec = FilesWorker::getProtonExecutable($gameName,'wineserver',true);
        $prefixDir = FilesWorker::getProtonPrefixPath($this->gamePanel->data('gameName'),'wine');
        
        if (fs::isDir($prefixDir) and $protonExec != false)
        {
            try{new Process([$protonExec,'-w'],null,['WINEPREFIX'=>$prefixDir])->startAndWait();}
            catch (Throwable $ex){}
        }
        
        uiLater(function () use ($gameName)
        {
            if ($this->gamePanel->data('gameName') == $gameName)
            {    
                if ($this->gamePanel->data('gameName') == $gameName)
                    $this->switchPlayButton('play');
                    
                $this->updateTimeSpent($gameName);
            }
        });
    }
    
    function switchPlayButton($status)
    {
        switch ($status)
        {
            case ('stop'):
                $this->playButton->text = $this->_('MAINFORM.STOP');
                $this->playButton->graphic = $this->playButton->data('stop');
                $this->gameDebugButton->enabled = false;
            break;
            case ('wait'):
                $this->playButton->text = $this->_('MAINFORM.WAIT');
                $this->playButton->graphic = $this->playButton->data('wait');
                $this->gameDebugButton->enabled = $this->playButton->enabled = false;
            return;
            default: 
                $this->playButton->text = $this->_('MAINFORM.PLAY');
                $this->playButton->graphic = $this->playButton->data('play');
                $this->gameDebugButton->enabled = true;
            break;
        }
        
        $this->playButton->enabled = true;
    }
    
    function updateTimeSpent($gameName)
    {
        $timeSpent = $this->appModule()->games->get('timeSpent',$gameName);
        if ($timeSpent < 3600)
        {
            $minutes = round($timeSpent / 60);
            $this->timeLabel->text = sprintf($this->_('MAINFORM.TIMESPENT.MINUTES'),$minutes >= 1 ? $minutes : 0);
        }
        else 
        {
            $this->timeLabel->text = sprintf($this->_('MAINFORM.TIMESPENT.HOURS'),round($timeSpent / 3600));
        }
    }
}
