<?php
namespace app\forms;

use std, gui, framework, app;


class launcherSettings extends AbstractForm
{
    /**
     * @event construct 
     */
    function doConstruct(UXEvent $e = null)
    {    
        $this->module('SettingsModule')->activePage = $this->paths;
    }

    /**
     * @event downloadsPath.click 
     */
    function doDownloadsPathClick(UXMouseEvent $e = null)
    {
        SettingsModule::setWithDirChooser('downloadsPath',$this->downloadsPath);
    }

    /**
     * @event vboxAlt.click 
     */
    function doVboxAltClick(UXMouseEvent $e = null)
    {
        $this->doDownloadsPathClick();
    }

    /**
     * @event vbox.click 
     */
    function doVboxClick(UXMouseEvent $e = null)
    {
        $this->doInstallsPathClick();
    }

    /**
     * @event installsPath.click 
     */
    function doInstallsPathClick(UXMouseEvent $e = null)
    {
        SettingsModule::setWithDirChooser('installsPath',$this->installsPath);
    }

    /**
     * @event vbox3.click 
     */
    function doVbox3Click(UXMouseEvent $e = null)
    {
        $this->doPrefixesPathClick();
    }

    /**
     * @event prefixesPath.click 
     */
    function doPrefixesPathClick(UXMouseEvent $e = null)
    {
        SettingsModule::setWithDirChooser('prefixesPath',$this->prefixesPath);
    }

    /**
     * @event downloadsPath.construct 
     */
    function doDownloadsPathConstruct(UXEvent $e = null)
    {    
        $e->sender->text = $this->appModule()->launcher->get('downloadsPath','User Settings') ?? 
                           str::trim(execute('xdg-user-dir DOWNLOAD',true)->getInput()->readFully());
    }

    /**
     * @event installsPath.construct 
     */
    function doInstallsPathConstruct(UXEvent $e = null)
    {    
        $e->sender->text = $this->appModule()->launcher->get('installsPath','User Settings') ?? Localization::getByCode('LAUNCHERSETTINGS.PATHS.NOPATH');
    }

    /**
     * @event prefixesPath.construct 
     */
    function doPrefixesPathConstruct(UXEvent $e = null)
    {    
        $e->sender->text = launcherSettings::getBasePathFor('prefixes');
    }

    /**
     * @event protonsList.construct 
     */
    function doProtonsListConstruct(UXEvent $e = null)
    {    
        $this->protonsList->setCellFactory(function (UXListCell $cell,$item)
        {
            $protonPath = launcherSettings::getBasePathFor('protons');
            $isInstalled = fs::isFile("$protonPath/".$item[0].'/proton');
            
            $label = new UXLabel($item[0]);
            $dnBtn = new UXMaterialButton;
            $hbox = new UXHBox;
            
            $hbox->spacing = 8;
            $hbox->alignment = 'CENTER_LEFT';
            
            $dnBtn->size = [20,20];
            $dnBtn->classesString = 'button jfx-button';
            $dnBtn->style = '-fx-background-radius:15px;';
            $dnBtn->cursor = 'HAND';
            $dnBtn->ripplerFill = '#f2f2f2';
            $dnBtn->contentDisplay = 'GRAPHIC_ONLY';
            
            $label->textColor = 'white';
            
            $refreshFunc = function () use ($item)
            {
                foreach ($this->protonsList->items->toArray() as $index => $cellItem)
                {
                    if ($cellItem[0] == $item[0])
                    {
                        $this->protonsList->items->removeByIndex($index);
                        if ($item[1] != null)
                            $this->protonsList->items->insert($index,[$item[0],$item[1]]);
                        break;
                    }
                }
            };
            $dnFunc = function () use ($item,$refreshFunc,$protonPath,$dnBtn)
            {
                $dnBtn->enabled = false;
                
                $downloader = app()->getNewForm('protonDownloader');
                
                $downloader->startDownload($item[0],$item[1]);
                $downloader->showAndWait();
                
                $dnBtn->enabled = true;
                
                if (fs::isFile("$protonPath/".$item[0].'/proton'))
                {
                    $this->defaultProton->items->clear();
                    
                    $this->doDefaultProtonConstruct();
                    $refreshFunc();
                }
            };
            $rmFunc = function () use ($item,$refreshFunc,$protonPath)
            {
                if (File::of(fs::abs("$protonPath/".$item[0]))->canWrite() == false)
                {
                    UXDialog::show(Localization::getByCode('ARIA.EXITCODE.17'),'ERROR');
                    return;
                }
                
                new Process(['rm','-rf',fs::abs("$protonPath/".$item[0])])->startAndWait();
                
                $refreshFunc();
            };
            
            if ($isInstalled)
            {
                $hoverWrap = new ColorAdjustEffectBehaviour;
                $hoverWrap->brightness = 0.15;
                $hoverWrap->when = 'HOVER';
                $hoverWrap->apply($dnBtn);
                
                $dnBtn->style .= '-fx-background-color:#fb2121;';
                $dnBtn->graphic = new UXImageArea(new UXImage('res://.data/img/remove.png'));
                $dnBtn->on('action',$rmFunc);
                
                $label->font = UXFont::of('System',12,'BOLD');
            }
            else 
            {
                $dnBtn->graphic = new UXImageArea(new UXImage('res://.data/img/download.png'));
                $dnBtn->on('action',$dnFunc);
                
                $label->font = UXFont::of('System',12);
            }
                
            $dnBtn->graphic->size = [15,15];
            $dnBtn->graphic->proportional = $dnBtn->graphic->centered = $dnBtn->graphic->stretch = true;
            
            $hbox->children->addAll([$dnBtn,$label]);
            
            $cell->graphic = $hbox;
            
            return $cell;
        });
        
        $installedProtons = FilesWorker::getInstalledProtons();
        foreach ($installedProtons as $proton)
            $this->protonsList->items->add([$proton]);
        
        new Thread(function () use ($installedProtons)
        {
            $releases = FilesWorker::fetchProtonReleases();
            if ($releases == false or str::contains($releases,'tar.gz'))
            {
                uiLater(function (){$this->toast(Localization::getByCode('GAMESETTINGS.NOGITHUBAPI'),4000);});
                
                $this->protonsList->data('allowRefresh',true);
                return;
            }
            
            foreach ($releases as $release)
            {
                foreach ($release['assets'] as $asset)
                {
                    if (Regex::match('^application/(gzip|x-gtar)$',$asset['content_type']) == false or 
                        $asset['state'] != 'uploaded' or 
                        $asset['browser_download_url'] == null)
                        continue;
                    
                    if (in_array($release['tag_name'],$installedProtons))
                    {
                        foreach ($this->protonsList->items->toArray() as $index => $item)
                        {
                            if ($item[0] == $release['tag_name'] and $item[1] == null)
                            {
                                uiLater(function () use ($index,$release,$asset)
                                {
                                    $this->protonsList->items->removeByIndex($index);
                                    $this->protonsList->items->insert($index,[$release['tag_name'],$asset['browser_download_url']]);
                                });
                                break;
                            }
                        }
                    }
                    else
                        uiLater(function () use ($release,$asset){$this->protonsList->items->add([$release['tag_name'],$asset['browser_download_url']]);});
                }
            }
        })->start();
    }


    /**
     * @event vbox4.click 
     */
    function doVbox4Click(UXMouseEvent $e = null)
    {
        $this->doProtonsPathClick();
    }

    /**
     * @event protonsPath.click 
     */
    function doProtonsPathClick(UXMouseEvent $e = null)
    {
        $result = SettingsModule::setWithDirChooser('protonsPath',$this->protonsPath);
        if ($result == false)
            return;
        
        $this->protonsList->items->clear();
        $this->defaultProton->items->clear();
        
        $this->appModule()->launcher->remove('defaultProton','User Settings');
        $this->doDefaultProtonConstruct();
        $this->doProtonsListConstruct();
    }

    /**
     * @event protonsPath.construct 
     */
    function doProtonsPathConstruct(UXEvent $e = null)
    {
        $e->sender->text = $this->appModule()->launcher->get('protonsPath','User Settings') ?? fs::abs('./protons');
    }

    /**
     * @event pathsButton.action 
     */
    function doPathsButtonAction(UXEvent $e = null)
    {    
        $this->switchPage($this->paths);
    }

    /**
     * @event protonsButton.action 
     */
    function doProtonsButtonAction(UXEvent $e = null)
    {    
        $this->switchPage($this->protons);
    }

    /**
     * @event defaultProton.construct 
     */
    function doDefaultProtonConstruct(UXEvent $e = null)
    {
        $this->defaultProton->items->addAll(array_merge(['GE-Proton Latest'],FilesWorker::getInstalledProtons()));
        $this->defaultProton->value = $this->appModule()->launcher->get('defaultProton','User Settings') ?? 'GE-Proton Latest';
    }

    /**
     * @event defaultProton.action 
     */
    function doDefaultProtonAction(UXEvent $e = null)
    {    
        $this->appModule()->launcher->set('defaultProton',$e->sender->value,'User Settings');
    }

    /**
     * @event launcherButton.action 
     */
    function doLauncherButtonAction(UXEvent $e = null)
    {    
        $this->switchPage($this->launcher);
    }

    /**
     * @event fullscreenLauncher.construct 
     */
    function doFullscreenLauncherConstruct(UXEvent $e = null)
    {
        $switch = new UXToggleSwitch;
        $switch->selected = $this->appModule()->launcher->get('fullscreen','User Settings');
        
        quUI::generateSetButton($e->sender,Localization::getByCode('LAUNCHERSETTINGS.LAUNCHER.FULLSCREEN'),$switch);
    }


    /**
     * @event version.construct 
     */
    function doVersionConstruct(UXEvent $e = null)
    {
        $e->sender->text = $GLOBALS['version'].' version';
    }
    /**
     * @event telegram.click 
     */
    function doTelegramClick(UXMouseEvent $e = null)
    {
        execute('xdg-open https://t.me/queinucentral');
    }

    /**
     * @event aboutButton.action 
     */
    function doAboutButtonAction(UXEvent $e = null)
    {    
        $this->switchPage($this->about);
    }

    /**
     * @event github.click 
     */
    function doGithubClick(UXMouseEvent $e = null)
    {    
        execute('xdg-open https://github.com/ZzEdovec/onlinefix-linux');
    }

    /**
     * @event fullscreenLauncher.action 
     */
    function doFullscreenLauncherAction(UXEvent $e = null)
    {    
        $this->appModule()->launcher->set('fullscreen',!$this->fullscreenLauncher->data('quUIElement')->selected,'User Settings');
        
        app()->form('MainForm')->fullScreen = !$this->fullscreenLauncher->data('quUIElement')->selected;
        uiLater(function (){$this->requestFocus();});
    }

    /**
     * @event label3.construct 
     */
    function doLabel3Construct(UXEvent $e = null)
    {    
        $e->sender->text = Localization::getByCode('LAUNCHERSETTINGS.PROTON.DEFAULT');
    }

    /**
     * @event pathsButton.construct 
     */
    function doPathsButtonConstruct(UXEvent $e = null)
    {    
        $e->sender->text = Localization::getByCode('LAUNCHERSETTINGS.TABS.PATHS');
    }

    /**
     * @event protonsButton.construct 
     */
    function doProtonsButtonConstruct(UXEvent $e = null)
    {    
        $e->sender->text = Localization::getByCode('LAUNCHERSETTINGS.TABS.PROTONS');
    }

    /**
     * @event launcherButton.construct 
     */
    function doLauncherButtonConstruct(UXEvent $e = null)
    {    
        $e->sender->text = Localization::getByCode('LAUNCHERSETTINGS.TABS.LAUNCHER');
    }

    /**
     * @event aboutButton.construct 
     */
    function doAboutButtonConstruct(UXEvent $e = null)
    {    
        $e->sender->text = Localization::getByCode('LAUNCHERSETTINGS.TABS.ABOUT');
    }

    /**
     * @event protonsList.action 
     */
    function doProtonsListAction(UXEvent $e = null)
    {    
        $e->sender->selectedIndex = -1;
    }

    /**
     * @event label5.construct 
     */
    function doLabel5Construct(UXEvent $e = null)
    {    
        $e->sender->text = Localization::getByCode('LAUNCHERSETTINGS.PATHS.HEADER');
    }

    /**
     * @event label4.construct 
     */
    function doLabel4Construct(UXEvent $e = null)
    {    
        $e->sender->text = Localization::getByCode('LAUNCHERSETTINGS.PATHS.DOWNLOADS');
    }

    /**
     * @event label.construct 
     */
    function doLabelConstruct(UXEvent $e = null)
    {    
        $e->sender->text = Localization::getByCode('LAUNCHERSETTINGS.PATHS.INSTALLS');
    }

    /**
     * @event label6.construct 
     */
    function doLabel6Construct(UXEvent $e = null)
    {    
        $e->sender->text = Localization::getByCode('LAUNCHERSETTINGS.PATHS.PROTONS');
    }

    /**
     * @event labelAlt.construct 
     */
    function doLabelAltConstruct(UXEvent $e = null)
    {    
        $e->sender->text = Localization::getByCode('LAUNCHERSETTINGS.PATHS.PREFIXES');
    }

    /**
     * @event label9.construct 
     */
    function doLabel9Construct(UXEvent $e = null)
    {    
        $e->sender->text = Localization::getByCode('LAUNCHERSETTINGS.PROTON.HINT');
    }

    /**
     * @event graphicsButton.action 
     */
    function doGraphicsButtonAction(UXEvent $e = null)
    {
        $this->switchPage($this->graphics);
    }

    /**
     * @event graphicsButton.construct 
     */
    function doGraphicsButtonConstruct(UXEvent $e = null)
    {
        $e->sender->text = Localization::getByCode('SETTINGSMODULE.GRAPHICS');
    }

    /**
     * @event wined3d.construct 
     */
    function doWined3dConstruct(UXEvent $e = null)
    {
        $switch = new UXToggleSwitch;
        $switch->selected = $this->appModule()->launcher->get('gamesUsesWined3d','User Settings');
        quUI::generateSetButton($e->sender,Localization::getByCode('SETTINGSMODULE.USEWINED3D'),$switch);
    }

    /**
     * @event wined3d.action 
     */
    function doWined3dAction(UXEvent $e = null)
    {
        $this->appModule()->launcher->set('gamesUsesWined3d',!($e->sender->data('quUIElement')->selected),'User Settings');
    }

    /**
     * @event useWayland.construct 
     */
    function doUseWaylandConstruct(UXEvent $e = null)
    {
        $switch = new UXToggleSwitch;
        $switch->selected = $this->appModule()->launcher->get('gamesUsesWayland','User Settings');
        
        quUI::generateSetButton($e->sender,Localization::getByCode('SETTINGSMODULE.NATIVEWAYLAND'),$switch);
    }

    /**
     * @event useWayland.action 
     */
    function doUseWaylandAction(UXEvent $e = null)
    {
        $this->appModule()->launcher->set('gamesUsesWayland',!($e->sender->data('quUIElement')->selected),'User Settings');
    }

    /**
     * @event show 
     */
    function doShow(UXWindowEvent $e = null)
    {    
        if ($this->protonsList->data('allowRefresh'))
        {
            $this->protonsList->items->clear();
            $this->doProtonsListConstruct();
        }
    }

    /**
     * @event requestSteam.construct 
     */
    function doRequestSteamConstruct(UXEvent $e = null)
    {
        $switch = new UXToggleSwitch;
        $switch->selected = $this->appModule()->launcher->get('noSteamRequest','User Settings');
        
        quUI::generateSetButton($e->sender,Localization::getByCode('LAUNCHERSETTINGS.LAUNCHER.REQUESTSTEAM'),$switch);
    }

    /**
     * @event requestSteam.action 
     */
    function doRequestSteamAction(UXEvent $e = null)
    {
        $this->appModule()->launcher->set('noSteamRequest',!$this->requestSteam->data('quUIElement')->selected,'User Settings');
    }

    /**
     * @event keyUp-Esc 
     */
    function doKeyUpEsc(UXKeyEvent $e = null)
    {    
        $this->hide();
    }

    /**
     * @event label10.construct 
     */
    function doLabel10Construct(UXEvent $e = null)
    {    
        $e->sender->text = Localization::getByCode('LAUNCHERSETTINGS.GRAPHICS.HEADER');
    }
    
    static function getBasePathFor($for)
    {
        $userHome = System::getProperty('user.home');
        $defaultDir = "$userHome/.local/share/OnlineFix Linux Launcher/$for";
        $userDir = app()->appModule()->launcher->get("$for\Path",'User Settings');
        
        if ($userDir == null)
        {
            fs::ensureParent($defaultDir);
            fs::makeDir($defaultDir);
            
            return $defaultDir;
        }
        
        return $userDir;
    }

}
