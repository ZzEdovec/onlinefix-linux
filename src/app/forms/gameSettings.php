<?php
namespace app\forms;

use php\io\IOException;
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
        $this->module('SettingsModule')->activePage = $this->view;
    }

















    /**
     * @event argsBefore.keyUp 
     */
    function doArgsBeforeKeyUp(UXKeyEvent $e = null)
    {
        $this->gamemode->data('quUIElement')->selected = str::contains($e->sender->text,'gamemoderun');
        $this->mangohud->data('quUIElement')->selected = str::contains($e->sender->text,'mangohud');
        $this->gamescope->data('quUIElement')->selected = str::contains($e->sender->text,'gamescope');
        
        $this->appModule()->games->set('argsBefore',$e->sender->text,$this->data('gameName'));
    }
















    /**
     * @event envBox.click 
     */
    function doEnvBoxClick(UXMouseEvent $e = null)
    {
        if ($this->appModule()->games->get('environment',$this->data('gameName')) != null)
            app()->form('envViewer')->loadByGame($this->data('gameName'));
        
        app()->form('envViewer')->title = $this->data('gameName').' environment';
        app()->showFormAndWait('envViewer');

        $this->env->text = str::replace(str::replace($this->appModule()->games->get('environment',$this->data('gameName')),'====','='),'\\\\\\\\',' ');
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
     * @event steamOverlay.construct 
     */
    function doSteamOverlayConstruct(UXEvent $e = null)
    {
        $switch = new UXToggleSwitch;
        $switch->selected = $this->appModule()->games->get('steamOverlay',$this->data('gameName'));
        quUI::generateSetButton($e->sender,Localization::getByCode('GAMESETTINGS.ADDITIONALS.USESTEAMOVERLAY'),$switch);
    }

    /**
     * @event steamRuntime.construct 
     */
    function doSteamRuntimeConstruct(UXEvent $e = null)
    {
        $switch = new UXToggleSwitch;
        $switch->selected = $this->appModule()->games->get('steamRuntime',$this->data('gameName'));
        quUI::generateSetButton($e->sender,Localization::getByCode('GAMESETTINGS.ADDITIONALS.USESTEAMRUNTIME'),$switch);
    }



    /**
     * @event label.construct 
     */
    function doLabelConstruct(UXEvent $e = null)
    {
        $e->sender->text = Localization::getByCode('GAMESETTINGS.STARTSETTINGS');
    }



    /**
     * @event argsAfter.keyUp 
     */
    function doArgsAfterKeyUp(UXKeyEvent $e = null)
    {    
        $this->appModule()->games->set('argsAfter',$e->sender->text,$this->data('gameName'));
    }

    /**
     * @event steamOverlay.action 
     */
    function doSteamOverlayAction(UXEvent $e = null)
    {
        $this->appModule()->games->set('steamOverlay',!($e->sender->data('quUIElement')->selected),$this->data('gameName'));
    }

    /**
     * @event steamRuntime.action 
     */
    function doSteamRuntimeAction(UXEvent $e = null)
    {
        if (Filesworker::findSteamRuntime() == false and !($e->sender->data('quUIElement')->selected))
        {
            if (uiConfirm(Localization::getByCode('GAMESETTINGS.STEAM.NORUNTIME')))
                execute('/usr/bin/steam steam://install/1628350');
            
            uiLater(function () use ($e){$e->sender->data('quUIElement')->selected = false;});
            return;
        }
        
        $this->appModule()->games->set('steamRuntime',!($e->sender->data('quUIElement')->selected),$this->data('gameName'));
    }

    /**
     * @event labelAlt.construct 
     */
    function doLabelAltConstruct(UXEvent $e = null)
    {    
        $e->sender->text = Localization::getByCode('GAMESETTINGS.ENVS.ARGS.BEFORE');
    }

    /**
     * @event label3.construct 
     */
    function doLabel3Construct(UXEvent $e = null)
    {    
        $e->sender->text = Localization::getByCode('GAMESETTINGS.ADDITIONALS');
    }

    /**
     * @event env.click 
     */
    function doEnvClick(UXMouseEvent $e = null)
    {    
        $this->doEnvBoxClick();
    }



    /**
     * @event vbox4.click 
     */
    function doVbox4Click(UXMouseEvent $e = null)
    {
        $this->argsAfter->requestFocus();
    }

    /**
     * @event label7.construct 
     */
    function doLabel7Construct(UXEvent $e = null)
    {
        $e->sender->text = Localization::getByCode('GAMESETTINGS.ENVS.ARGS.AFTER');
    }

    /**
     * @event viewButton.action 
     */
    function doViewButtonAction(UXEvent $e = null)
    {
        $this->switchPage($this->view);
    }

    /**
     * @event startupButton.action 
     */
    function doStartupButtonAction(UXEvent $e = null)
    {
        $this->switchPage($this->startup);
    }





    /**
     * @event applyGameName.construct 
     */
    function doApplyGameNameConstruct(UXEvent $e = null)
    {
        $e->sender->graphic = new UXImageArea(new UXImage('res://.data/img/ok.png'));
        $e->sender->graphic->size = [14,14];
    }

    /**
     * @event applyGameName.action 
     */
    function doApplyGameNameAction(UXEvent $e = null)
    {
        $gameName = $this->data('gameName');
        $gameSettings = $this->appModule()->games->section($gameName);
        $desktopPath = str::trim(execute('xdg-user-dir DESKTOP',true)->getInput()->readFully());
        $menuPath = System::getProperty('user.home').'/.local/share/applications';
        
        if (fs::isFile("$desktopPath/$gameName.desktop"))
        {
            fs::delete("$desktopPath/$gameName.desktop");
            $desktop = true;
        }
        if (fs::isFile("$menuPath/$gameName.desktop"))
        {
            fs::delete("$menuPath/$gameName.desktop");
            $menu = true;
        }
        
        $gameNameNew = $this->gameName->text;
        
        $this->appModule()->games->removeSection($gameName);
        $this->appModule()->games->put($gameSettings,$gameNameNew);
        
        $this->title = $gameNameNew;
        $this->data('gameName',$gameNameNew);
        if (app()->form('MainForm')->gamePanel->data('gameName') == $gameName)
        {
            app()->form('MainForm')->gamePanel->data('gameName',$gameNameNew);
            app()->form('MainForm')->gamePanel->data('opener')->children[3]->children[0]->text = $gameNameNew;
        }
        else 
        {
            foreach (app()->form('MainForm')->container->content->children->toArray() as $game)
            {
                if ($game->children[3]->children[0]->text == $gameName)
                {
                    $game->children[3]->children[0]->text = $gameNameNew;
                    break;
                }
            }
        }
        
        if ($desktop)
        {
            file_put_contents("$desktopPath/$gameNameNew.desktop",FilesWorker::generateDesktopEntry($gameNameNew,$gameSettings['icon']));
            new Process(['chmod','+x',$desktopPath])->start();
        }
        if ($menu) {file_put_contents("$menuPath/$gameNameNew.desktop",FilesWorker::generateDesktopEntry($gameNameNew,$gameSettings['icon']));}
        
        $e->sender->enabled = false;
    }



    /**
     * @event editIcon.construct 
     */
    function doEditIconConstruct(UXEvent $e = null)
    {
        $e->sender->graphic = new UXImageArea(new UXImage('res://.data/img/edit.png'));
        $e->sender->graphic->size = [14,14];
    }

    /**
     * @event editIcon.action 
     */
    function doEditIconAction(UXEvent $e = null)
    {
        $fc = new UXFileChooser;
        $fc->extensionFilters = [['extensions'=>['*.png','*.jpg','*.exe'],'description'=>Localization::getByCode('FILECHOOSER.IMG.DESC')]];
        
        $icon = $fc->showOpenDialog($this);
        if ($icon == null)
            return;
            
        $userHome = System::getProperty('user.home');
        $desktopPath = str::trim(execute('xdg-user-dir DESKTOP',true)->getInput()->readFully());
        $menuPath = "$userHome/.local/share/applications";
        $oldIcon = $this->appModule()->games->get('icon',$this->data('gameName'));
            
        if (fs::ext($icon) == 'exe')
        {
            try 
            {
                $iconPath = FixParser::parseIcon($icon);
                if (fs::isFile($iconPath) == false)
                    throw new IOException('File not found');
            }
            catch (Throwable $ex) {UXDialog::show(sprintf(Localization::getByCode('MAINFORM.ICONPARSERERROR'),$ex->getMessage()),'ERROR'); return;}
        }
        else 
        {
            $iconPath = "$userHome/.config/OFME-Linux/icons/".fs::nameNoExt($icon);
            
            fs::makeDir("$userHome/.config/OFME-Linux/icons");
            fs::copy($icon,$iconPath);
        }
        
        $this->appModule()->games->set('icon',$iconPath,$this->data('gameName'));
        $this->gameIcon->image = new UXImage($iconPath);
        
        if (app()->form('MainForm')->gamePanel->data('gameName') == $this->data('gameName'))
            app()->form('MainForm')->gamePanel->data('opener')->children[3]->children[0]->graphic->image = $this->gameIcon->image;
        else 
        {
            foreach (app()->form('MainForm')->container->content->children->toArray() as $game)
            {
                if ($game->children[3]->children[0]->text == $gameName)
                {
                    $game-->children[3]->children[0]->graphic->image = $this->gameIcon->image;
                    break;
                }
            }
        }
        
        fs::delete($oldIcon);
        
        if (fs::isFile("$desktopPath/".$this->data('gameName').'.desktop'))
            file_put_contents("$desktopPath/".$this->data('gameName').'.desktop',str::replace
            (file_get_contents("$desktopPath/".$this->data('gameName').'.desktop'),$oldIcon,$iconPath));
        if (fs::isFile("$menuPath/".$this->data('gameName').'.desktop'))
            file_put_contents("$menuPath/".$this->data('gameName').'.desktop',str::replace
            (file_get_contents("$menuPath/".$this->data('gameName').'.desktop'),$oldIcon,$iconPath));
    }

    /**
     * @event editBanner.construct 
     */
    function doEditBannerConstruct(UXEvent $e = null)
    {
        $menu = new UXContextMenu;
        $viaSteam = new UXMenuItem(Localization::getByCode('BANNEREDITOR.STEAM.HEADER'));
        $fromFile = new UXMenuItem(Localization::getByCode('BANNEREDITOR.FILE.HEADER'));
        
        $viaSteam->on('action',function ()
        {
            $steamID = UXDialog::input(Localization::getByCode('BANNEREDITOR.PROMPT'),$this->appModule()->games->get('steamID',$this->data('gameName')));
            
            if ($steamID == null)
            {
                $this->toast(Localization::getByCode('BANNEDEDITOR.STEAM.NOAPPID'),3000);
                return;
            }
            
            
            $banner = FixParser::parseBanner($steamID);
            if (fs::isFile($banner) == false)
            {
                UXDialog::show($banner,'ERROR');
                return;
            }

            $this->setBanner(new UXImage($banner));
                
        });
        $fromFile->on('action',function ()
        {
            $fc = new UXFileChooser;
        
            $fc->extensionFilters = [['extensions'=>['*.jpg','*.png'],'description'=>Localization::getByCode('FILECHOOSER.IMG.DESC')]];
            $fc->title = Localization::getByCode('FILECHOOSER.IMG.TITLE');
            
            $banner = $fc->showOpenDialog($this);
            if ($banner == null)
                return;
            
            $banner = new UXImage($banner);
            $this->setBanner($banner);
        });
        
        $menu->items->addAll([$viaSteam,$fromFile]);
        
        $e->sender->data('menu',$menu);
        $e->sender->graphic = new UXImageArea(new UXImage('res://.data/img/edit.png'));
        $e->sender->graphic->size = [14,14];
    }

    /**
     * @event editBanner.click 
     */
    function doEditBannerClick(UXMouseEvent $e = null)
    {
        $e->sender->data('menu')->showByNode($e->sender,$e->x,$e->y);
    }

    /**
     * @event gameName.keyUp 
     */
    function doGameNameKeyUp(UXKeyEvent $e = null)
    {
        if ($e->sender->text == $this->data('gameName') or $e->sender->text == null)
        {
            $this->applyGameName->enabled = false;
            return;
        }
        
        $this->applyGameName->enabled = true;
    }

    /**
     * @event hide 
     */
    function doHide(UXWindowEvent $e = null)
    {    
        $this->free();
    }

    /**
     * @event gameName.construct 
     */
    function doGameNameConstruct(UXEvent $e = null)
    {    
        $e->sender->promptText = Localization::getByCode('GAMESETTINGS.GAMENAME.PROMPT');
        $e->sender->text = $this->data('gameName');
    }

    /**
     * @event vboxAlt.click 
     */
    function doVboxAltClick(UXMouseEvent $e = null)
    {
        if ($this->appModule()->games->get('environment',$this->data('gameName')) != null)
            app()->form('envViewer')->loadByGame($this->data('gameName'));
            
        quUI::showFormAndFocus('envViewer');
    }



    /**
     * @event overrides.construct 
     */
    function doOverridesConstruct(UXEvent $e = null)
    {
        $e->sender->text = $this->appModule()->games->get('overrides',$this->data('gameName'));
    }

    /**
     * @event overrides.keyUp 
     */
    function doOverridesKeyUp(UXKeyEvent $e = null)
    {    
        $this->appModule()->games->set('overrides',$e->sender->text,$this->data('gameName'));
    }

    /**
     * @event env.construct 
     */
    function doEnvConstruct(UXEvent $e = null)
    {    
        $e->sender->text = str::replace(str::replace($this->appModule()->games->get('environment',$this->data('gameName')),'====','='),'\\\\\\\\',' ');
    }

    /**
     * @event argsBefore.construct 
     */
    function doArgsBeforeConstruct(UXEvent $e = null)
    {    
        $e->sender->text = $this->appModule()->games->get('argsBefore',$this->data('gameName'));
    }

    /**
     * @event argsAfter.construct 
     */
    function doArgsAfterConstruct(UXEvent $e = null)
    {    
        $e->sender->text = $this->appModule()->games->get('argsAfter',$this->data('gameName'));
    }

    /**
     * @event proton.construct 
     */
    function doProtonConstruct(UXEvent $e = null)
    {
        $this->proton->items->addAll(array_merge(['GE-Proton Latest',Localization::getByCode('GAMESETTINGS.PROTONS.OTHER')],FilesWorker::getInstalledProtons()));
        $this->proton->value = $this->appModule()->games->get('proton',$this->data('gameName')) ?? 'GE-Proton Latest';
    }

    /**
     * @event proton.action 
     */
    function doProtonAction(UXEvent $e = null)
    {
        if ($this->proton->value == Localization::getByCode('GAMESETTINGS.PROTONS.OTHER'))
        {
            $form = quUI::showFormAndFocus('launcherSettings');
            uiLater(function () use ($form)
            {
                $form->protonsButton->selected = true;
                $form->doProtonsButtonAction();
                
                $this->proton->value = $this->appModule()->games->get('proton',$this->data('gameName'));
            });
            return;
        }
        
        $this->appModule()->games->set('proton',$this->proton->value,$this->data('gameName'));
    }

    /**
     * @event prefixPath.click 
     */
    function doPrefixPathClick(UXMouseEvent $e = null)
    {    
        $dc = new UXDirectoryChooser;
        
        $prefixPath = $dc->showDialog($this);
        if ($prefixPath == null)
            return;
        elseif (File::of($prefixPath)->findFiles() != [])
            UXDialog::show(Localization::getByCode('NEWGAMECONFIG.PREFIX.PATHNONEMPTY'),'WARNING');
            
        $oldPrefix = $this->appModule()->games->get('prefixPath',$this->data('gameName')) ?? 
                     fs::parent($this->appModule()->games->get('executable',$this->data('gameName'))).'/OFME Prefix';
        
        $oldFiles = File::of($oldPrefix)->findFiles();
        if ($oldFiles != [] and uiConfirm(Localization::getByCode('GAMESETTINGS.PROTONS.PREFIXMOVE')))
        {
            foreach ($oldFiles as $file)
                new Process(['mv','-f',$file,$prefixPath])->start();
                
            fs::delete($oldPrefix);
        }
            
        $this->appModule()->games->set('prefixPath',$prefixPath,$this->data('gameName'));
        $this->prefixPath->text = $prefixPath;
    }

    /**
     * @event prefixPath.construct 
     */
    function doPrefixPathConstruct(UXEvent $e = null)
    {    
        $this->prefixPath->text = $this->appModule()->games->get('prefixPath',$this->data('gameName')) ?? 
                                  fs::parent($this->appModule()->games->get('executable',$this->data('gameName'))).'/OFME Prefix';
    }

    /**
     * @event label5.construct 
     */
    function doLabel5Construct(UXEvent $e = null)
    {    
        $e->sender->text = Localization::getByCode('GAMESETTINGS.PROTONS.VERSION');
    }

    /**
     * @event label8.construct 
     */
    function doLabel8Construct(UXEvent $e = null)
    {    
        $e->sender->text = Localization::getByCode('GAMESETTINGS.PROTONS.PREFIXPATH');
    }

    /**
     * @event viewButton.construct 
     */
    function doViewButtonConstruct(UXEvent $e = null)
    {    
        $e->sender->text = Localization::getByCode('GAMESETTINGS.TABS.VIEW');
    }

    /**
     * @event startupButton.construct 
     */
    function doStartupButtonConstruct(UXEvent $e = null)
    {    
        $e->sender->text = Localization::getByCode('GAMESETTINGS.TABS.RUN');
    }














    
    
    function setBanner(UXImage $banner)
    {
        try 
        {
            $this->banner->image = $banner;
            
            $mainForm = app()->form('MainForm');
            $mainForm->gameHeader->image = $banner;
            $mainForm->gamePanel->data('opener')->children[1]->image = $banner;
            if ($mainForm->background != null and $mainForm->background->visible) {$mainForm->background->image = $banner;}
            
            $gameName = $this->data('gameName');
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
