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
        $fPane->hgap = $fPane->vgap = $fPane->paddingTop = $fPane->paddingRight = $fPane->paddingLeft = 15;
        
        $e->sender->content = $fPane;
    }

    /**
     * @event addGame.action 
     */
    function doAddGameAction(UXEvent $e = null)
    {    
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
        
        $appName = UXDialog::input(Localization::getByCode('MAINFORM.SETGAMENAME'),fs::nameNoExt($exe),$this) ?? fs::nameNoExt($exe); 
        if ($this->appModule()->games->section($appName) != [])
        {
            UXDialog::show(Localization::getByCode('MAINFORM.GAMEEXISTS'),'ERROR',$this);
            return;
        }
        
        $files = fs::scan($appPath,['excludeDirs'=>true,'namePattern'=>
                                                                 'winmm.dll|[Oo]nline[Ff]ix.*\.(dll|ini)$|[Ee][Oo][Ss].*\.dll$|[Ss]team.*\.dll$|dlllist.txt']);
                                                                 
        $overrides = FixParser::parseDlls($files);
        if ($overrides['overrides'] == null)
        {
            UXDialog::show(Localization::getByCode('MAINFORM.NOFIX'),'ERROR',$this);
            return;
        }
        elseif (str::contains($overrides['overrides'],'steam') == false and str::contains($overrides['overrides'],'eos'))
            UXDialog::show(Localization::getByCode('MAINFORM.EOSFIX'),'WARNING');
            
        if ($overrides['realAppId'] != null)
        {
            try
            {
                $url = FixParser::parseBanner($overrides['realAppId']);
                $imagesDir = System::getProperty('user.home').'/.config/OFME-Linux/banners';
                
                fs::makeDir($imagesDir);
                fs::copy($url,$imagesDir.'/'.$appName.'.jpg');
                
                $image = $imagesDir.'/'.$appName.'.jpg';
                $this->appModule()->games->set('banner',$image,$appName);
            } catch (Throwable $ex) {}
        }
        
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
            }
        } catch (Throwable $ex) {UXDialog::show(sprintf(Localization::getByCode('MAINFORM.ICONPARSERERROR'),$ex->getMessage()),'ERROR',$this);}
        
        $this->appModule()->games->set('overrides',$overrides['overrides'],$appName);
        $this->appModule()->games->set('executable',$exe,$appName);
        
        
        $this->addGame($appName,$exe,$overrides['overrides'],$image,$iconPath);
    }

    /**
     * @event construct 
     */
    function doConstruct(UXEvent $e = null)
    {
        if (fs::isFile('/usr/bin/gamemoderun') == false)
            $this->gamemode->enabled = false;
        else
            $this->gamemode->selected = $this->appModule()->settings->get('useGamemode');
        
        foreach ($this->appModule()->games->toArray() as $name => $params)
        {
            $this->addGame($name,$params['executable'],$params['overrides'],$params['banner'],$params['icon']);
        }
    }

    /**
     * @event gamemode.click 
     */
    function doGamemodeClick(UXMouseEvent $e = null)
    {    
        $this->appModule()->settings->set('useGamemode',$e->sender->selected);
    }

    /**
     * @event noGamesHeader.construct 
     */
    function doNoGamesHeaderConstruct(UXEvent $e = null)
    {    
        $e->sender->text = Localization::getByCode('MAINFORM.HEADER');
    }

    /**
     * @event noGamesSubHeader.construct 
     */
    function doNoGamesSubHeaderConstruct(UXEvent $e = null)
    {    
        $e->sender->text = Localization::getByCode('MAINFORM.SUBHEADER');
    }

    /**
     * @event gamemode.construct 
     */
    function doGamemodeConstruct(UXEvent $e = null)
    {    
        $e->sender->text = Localization::getByCode('MAINFORM.GAMEMODE');
    }

    /**
     * @event addGame.construct 
     */
    function doAddGameConstruct(UXEvent $e = null)
    {    
        $e->sender->text = Localization::getByCode('MAINFORM.ADDGAME');
    }

    /**
     * @event button.action 
     */
    function doButtonAction(UXEvent $e = null)
    {
        execute('xdg-open https://www.donationalerts.com/r/queinu');
    }

    /**
     * @event button.construct 
     */
    function doButtonConstruct(UXEvent $e = null)
    {
        $e->sender->text = Localization::getByCode('MAINFORM.DONATE');
    }
    
    function addGame($gameName,$exec,$overrides,$image = null,$icon = null)
    {
        $gamePanel = $this->instance('prototypes.panel');
        
        $gamePanel->children[2]->text = $gameName;
        $gamePanel->children[1]->image = new UXImage(fs::isFile($image) ? $image : 'res://.data/img/noImage.png');
        $gamePanel->children[9]->image = new UXImage(fs::isFile($icon) ? $icon : 'res://.data/img/noImage.png');
        $gamePanel->children[6]->image = new UXImage('res://.data/img/more.png');
        $gamePanel->children[6]->cursor = 'HAND';
        $gamePanel->children[4]->classesString = 'button menu-button';
        $gamePanel->children[4]->cursor = 'HAND';
        $gamePanel->children[4]->graphic = new UXImageView(new UXImage('res://.data/img/play.png'));
        $menu = new UXContextMenu;
        
        $desktopIcon = new UXMenuItem(fs::isFile($this->appModule()->games->get('desktopIcon',$gameName)) ? Localization::getByCode('MAINFORM.MENU.REMOVEDESKTOP') : Localization::getByCode('MAINFORM.MENU.CREATEDESKTOP'));
        $appMenuIcon = new UXMenuItem(fs::isFile($this->appModule()->games->get('appMenuIcon',$gameName)) ? Localization::getByCode('MAINFORM.MENU.REMOVEAPPMENU') : Localization::getByCode('MAINFORM.MENU.CREATEAPPMENU'));
        $separator = UXMenuItem::createSeparator();
        $bannerEdit = new UXMenuItem(Localization::getByCode('MAINFORM.MENU.EDITBANNER'));
        $separatorAlt = UXMenuItem::createSeparator();
        $overridesEdit = new UXMenuItem(Localization::getByCode('MAINFORM.MENU.EDITDLLS'));
        $separator3 = UXMenuItem::createSeparator();
        $libraryDelete = new UXMenuItem(Localization::getByCode('MAINFORM.MENU.REMOVEGAME'));
        
        $menu->items->addAll([$desktopIcon,$appMenuIcon,$separator,$bannerEdit,$separatorAlt,$overridesEdit,$separator3,$libraryDelete]);
        
        $desktopEntry = filesWorker::generateDesktopEntry($gameName,$icon);
        $desktopIcon->on('action',function () use ($desktopEntry,$gameName,$desktopIcon)
        {
            $desktopPath = str::trim(execute('xdg-user-dir DESKTOP',true)->getInput()->readFully()).'/'.$gameName.'.desktop';
            
            if (fs::isFile($desktopPath))
            {
                fs::delete($desktopPath);
                
                $desktopIcon->text = Localization::getByCode('MAINFORM.MENU.CREATEDESKTOP');
                $this->appModule()->games->remove('desktopIcon',$gameName);
            }
            else 
            {
                file_put_contents($desktopPath,$desktopEntry);
                new Process(['chmod','+x',$desktopPath])->start();
                
                $desktopIcon->text = Localization::getByCode('MAINFORM.MENU.REMOVEDESKTOP');
                $this->appModule()->games->set('desktopIcon',$desktopPath,$gameName);
            }
        });
        $appMenuIcon->on('action',function () use ($desktopEntry,$gameName,$appMenuIcon)
        {
            $appMenuPath = System::getProperty('user.home')."/.local/share/applications/$gameName.desktop";
            
            if (fs::isFile($appMenuPath))
            {
                fs::delete($appMenuPath);
                
                $appMenuIcon->text = Localization::getByCode('MAINFORM.MENU.CREATEAPPMENU');
                $this->appModule()->games->remove('appMenuIcon',$gameName);
            }
            else 
            {
                file_put_contents($appMenuPath,$desktopEntry);
                
                $appMenuIcon->text = Localization::getByCode('MAINFORM.MENU.REMOVEAPPMENU');
                $this->appModule()->games->set('appMenuIcon',$appMenuPath,$gameName);
            }
        });
        $bannerEdit->on('action',function () use ($gamePanel,$gameName)
        {
            app()->showFormAndWait('bannerEditor');
            
            $banner = app()->form('bannerEditor')->data('banner');
            
            if ($banner == null)
                return;
            
            $gamePanel->children[1]->image->cancel();
            $gamePanel->children[1]->image = $banner;
            
            $bannersPath = System::getProperty('user.home').'/.config/OFME-Linux/banners';
            $currentBanner = $this->appModule()->games->get('banner',$gameName);
            
            if ($currentBanner != null)
                fs::delete($currentBanner);
            else 
                fs::makeDir($bannersPath);
            
            $banner->save($bannersPath.'/'.$gameName.'.png');
            $this->appModule()->games->set('banner',$bannersPath.'/'.$gameName.'.png',$gameName);
            
            app()->form('bannerEditor')->free();
        });
        $overridesEdit->on('action',function () use ($overrides,$gameName)
        {
            $overrides = UXDialog::input(Localization::getByCode('MAINFORM.EDITDLLS'),$overrides,$this) ?? $overrides;
                
            $this->appModule()->games->set('overrides',$overrides,$gameName);
        });
        $libraryDelete->on('action',function () use ($gameName,$gamePanel)
        {
            fs::delete($this->appModule()->games->get('desktopIcon',$gameName));
            fs::delete($this->appModule()->games->get('appMenuIcon',$gameName));
            
            $this->appModule()->games->removeSection($gameName);
            $gamePanel->free();
            
            if ($this->container->content->children->isEmpty())
            {
                $this->noGamesHeader->show();
                $this->noGamesSubHeader->show();
            }
        });
        $gamePanel->children[6]->on('click',function () use ($menu,$gamePanel)
        {
            $menu->showByNode($gamePanel->children[6],0,$gamePanel->children[6]->height);
        });
        $gamePanel->children[4]->on('click',function ($e) use ($gameName)
        {
            $process = filesWorker::generateProcess($gameName);
            if ($process == null)
                return;
            
            
            $e->sender->enabled = false;
            new Thread(function () use ($e,$process)
            {
                $process->startAndWait();
                
                uiLater(function () use ($e){$e->sender->enabled = true;});
            })->start();
        });
        
        $this->container->content->children->add($gamePanel);
        
        if ($this->noGamesHeader->visible)
        {
            $this->noGamesHeader->visible = $this->noGamesSubHeader->visible = false;
        }
    }

}
