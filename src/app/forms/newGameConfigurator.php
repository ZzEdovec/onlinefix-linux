<?php
namespace app\forms;

use php\gui\controlsfx\UXToggleSwitch;
use Throwable;
use php\io\IOException;
use std, gui, framework, app;


class newGameConfigurator extends AbstractForm
{

    $gameParams;

    /**
     * @event gamePathBox.click 
     */
    function doGamePathBoxClick(UXMouseEvent $e = null)
    {
        $this->doGamePathClick();
    }

    /**
     * @event vbox4.click 
     */
    function doVbox4Click(UXMouseEvent $e = null)
    {
        $this->doPrefixPathClick();
    }

    /**
     * @event listView.construct 
     */
    function doListViewConstruct(UXEvent $e = null)
    {    
        $e->sender->setCellFactory(function (UXListCell $cell, $item) use ($e)
        {
            if (is_array($item))
            {
                $vbox = new UXVbox;
                $vbox->spacing = 0;
                $vbox->alignment = 'CENTER_LEFT';
                
                $labels = [new UXLabel($item[0]),new UXLabel($item[1])];
                $labels[0]->font = UXFont::of('System',12,'BOLD');
                $labels[1]->font = UXFont::of('System',12);
                $labels[0]->textColor = 'White';
                $labels[1]->textColor = '#e6e6e6';
                
                $vbox->children->addAll($labels);
                $cell->graphic = $vbox;
                
                $cell->data('canInstall',$item[2] ?? true);
            }
            else 
                $cell->text = $item;
            
            return $cell;
        });
    }

    /**
     * @event vbox6.click 
     */
    function doVbox6Click(UXMouseEvent $e = null)
    {
        $this->gameName->requestFocus();
    }


    /**
     * @event hide 
     */
    function doHide(UXWindowEvent $e = null)
    {
        if ($this->isFree())
            return;
            
        $this->free();
        $this->doCancelButtonAction();
    }

    /**
     * @event prefixPath.click 
     */
    function doPrefixPathClick(UXMouseEvent $e = null)
    {    
        $dc = new UXDirectoryChooser;
        
        $prefixDir = $dc->showDialog($this);
        if ($prefixDir == null)
            return;
        elseif (File::of($prefixDir)->findFiles() != [])
            UXDialog::show(Localization::getByCode('NEWGAMECONFIG.PREFIX.PATHNONEMPTY'),'WARNING');
        
        $this->prefixPath->text = $prefixDir;
    }

    /**
     * @event gamePath.click 
     */
    function doGamePathClick(UXMouseEvent $e = null)
    {    
        $dc = new UXDirectoryChooser;
        
        $gameDir = $dc->showDialog($this);
        if ($gameDir == null)
            return;
            
        $this->gamePath->text = $gameDir;
    }
    
    /**
     * @event selectFileButton.action 
     */
    function doSelectFileButtonAction(UXEvent $e = null,$candidate = null)
    {    
        if (is_null($this->listView->selectedIndex))
        {
            $this->toast(Localization::getByCode('NOTHING.SELECTED'));
            return;
        }
        
        if ($candidate == null)
        {
            $canInstall = $this->listView->selectedItem[2];
            $candidate = $this->gameParams['path'].$this->listView->selectedItem[1].$this->listView->selectedItem[0];
        }
        else
        {
            $canInstall = $candidate[2];
            $candidate = $this->gameParams['path'].$candidate[1].$candidate[0];
        }
        
        $nameNoExt = fs::nameNoExt($candidate);
        $ext = fs::ext($candidate);
        
        $isLauncher = str::contains(str::lower($nameNoExt),'launcher');
        if ($canInstall)
        {
            Logger::info('RAR selected, so parsing archive content');
            
            $this->gameParams['originalFile'] = $candidate;
            $this->parseFromRar($candidate);
            
            return;
        }
        elseif ($ext == 'exe' and $isLauncher == false) {$this->gameName->text = $nameNoExt;}
        else
        {
            $this->gameName->text = fs::name(fs::parent($candidate));
            $nameNoExt = $this->gameName->text;
        }
        
        if ($this->gameParams['path'] != null)
        {
            $this->gamePathBox->enabled = false;
            $this->cleanAfterAdd->enabled = $this->cleanAfterAdd->data('quUIElement')->selected = false;
        }
        
        $prefixPath = launcherSettings::getBasePathFor('prefixes');
        
        $this->gamePath->text = $this->gameParams['path'] ?? $this->appModule()->launcher->get('installsPath','User Settings'); 
        $this->prefixPath->text = "$prefixPath/$nameNoExt";
        
        $this->gameParams['mainFile'] = $candidate;
        
        $this->mainSelectBox->free();
        $this->gameParamsBox->show();
    }
    
    function parseFromRar($file)
    {
        try 
        {
            $extractor = new RarExtractor;
            $files = $extractor->getRarContent($file);
        }
        catch (Throwable $ex)
        {
            $files = $extractor->retryWithEnsureError($ex->getMessage(),$file);
            if ($files == null)
            {
                $this->hide();
                return;
            }
        }
        
        $parsedFiles = [];
        foreach ($files as $file)
            $parsedFiles[] = "/$file";
            
        if ($parsedFiles == [])
        {
            UXDialog::showAndWait(Localization::getByCode('NEWGAMECONFIG.EMPTYRAR'),'ERROR');
            $this->hide();
            return;
        }
        
        $this->prepareForGame($parsedFiles);
    }

    /**
     * @event cancelButton.action 
     */
    function doCancelButtonAction(UXEvent $e = null)
    {
        if ($this->gameParams['openedFromAria'] and ($this->isFree() or uiConfirm(Localization::getByCode('NEWGAMECONFIG.ARESURE'))))
        {
            $origParent = fs::parent($this->gameParams['originalFile']);

            new Process(['rm','-rf',$origParent])->start();
            $this->hide();
        }
        elseif ($this->gameParams['openedFromAria'] == false and $this->isFree() == false)
            $this->hide();
    }

    /**
     * @event gameName.keyUp 
     */
    function doGameNameKeyUp(UXKeyEvent $e = null)
    {
        $parent = fs::parent($this->prefixPath->text);
        $default = launcherSettings::getBasePathFor('prefixes');
        
        if ($parent == $default)
            $this->prefixPath->text = $parent.'/'.$e->sender->text;
    }

    /**
     * @event addGame.action 
     */
    function doAddGameAction(UXEvent $e = null)
    {    
        $isAddPossible = $this->checkAreAddPossible();
        if (is_bool($isAddPossible) == false or $isAddPossible != true)
        {
            UXDialog::show($isAddPossible,'ERROR');
            return;
        }
        
        $this->free();
        
        $box = app()->form('MainForm')->addStubGame();
        $box['gameName']->text = $this->gameName->text;
        
        new Thread(function () use ($box)
        {
            if ($this->gameParams['originalFile'] != null)
            {
                uiLater(function () use ($box){$box['status']->text = Localization::getByCode('NEWGAMECONFIG.UNPACKING');});
                
                $extractor = new RarExtractor;
                try 
                {
                    $extractor->unpackRar($this->gameParams['originalFile'],$this->gamePath->text);
                } catch (Throwable $ex)
                {
                    $result = $extractor->retryWithEnsureError($ex->getMessage(),$this->gameParams['originalFile'],$this->gamePath->text);
                    if ($result != true)
                    {
                        uiLater(function () use ($box) {app()->form('MainForm')->removeStubGame($box['box']);});
                        return;
                    }
                }
            }
            
            if (fs::isFile($this->gameParams['mainFile']) == false)
                $this->gameParams['mainFile'] = $this->gamePath->text.$this->gameParams['mainFile'];
            
            if ($this->gameParams['unpackedPath'] != null)
                $path = $this->gamePath->text .= $this->gameParams['unpackedPath'];
            
            uiLater(function () use ($box){$box['status']->text = Localization::getByCode('NEWGAMECONFIG.DLLS');});
            
            $parsed = FixParser::parseDlls($path ?? $this->gamePath->text);
            if ($parsed['fakeAppId'] != null)
                $this->appModule()->games->set('fakeSteamID',$parsed['fakeAppId'],$this->gameName->text);
                
            if ($parsed['realAppId'] != null)
            {
                $bannerPath = FixParser::parseBanner($parsed['realAppId']);
                if (fs::isFile($bannerPath))
                    $this->appModule()->games->set('banner',$bannerPath,$this->gameName->text);
                else 
                    uiLater(function () use ($bannerPath){UXDialog::show($bannerPath,'ERROR');});
                
                $this->appModule()->games->set('steamID',$parsed['realAppId'],$this->gameName->text);
            }
                
            uiLater(function () use ($box){$box['status']->text = Localization::getByCode('NEWGAMECONFIG.ICOEXTRACT');});
            
            try
            {
                $appIcon = FixParser::parseIcon($this->gameParams['mainFile']);
                if (fs::isFile($appIcon))
                    $this->appModule()->games->set('icon',$appIcon,$this->gameName->text);
            } catch (Throwable $ex)
            {
                uiLater(function () use ($ex)
                {
                    UXDialog::show(sprintf(Localization::getByCode('MAINFORM.ICONPARSERERROR'),$ex->getMessage()),'ERROR');
                });
            }
            
            uiLater(function () use ($box){$box['status']->text = Localization::getByCode('NEWGAMECONFIG.SETTINGS');});
            
            $this->appModule()->games->put(['overrides'=>$parsed['overrides'],
                                            'executable'=>$this->gameParams['mainFile'],
                                            'mainPath'=>$path ?? $this->gamePath->text,
                                            'prefixPath'=>$this->prefixPath->text,
                                            'proton'=>$this->appModule()->launcher->get('defaultProton','User Settings') ?? 'GE-Proton Latest',
                                            'steamRuntime'=>FilesWorker::findSteamRuntime() != false ? true : false,
                                            'steamOverlay'=>true],$this->gameName->text);
                                            
            uiLater(function () use ($parsed,$bannerPath,$appIcon,$box)
            {
                app()->form('MainForm')->removeStubGame($box['box']);
                
                app()->form('MainForm')->addGame($this->gameName->text,$this->gameParams['mainFile'],$parsed['overrides'],$bannerPath,$appIcon);
            });
            
            if ($this->cleanAfterAdd->data('quUIElement')->selected) #available only when installed (not simple added), so no checks needed
            {
                $origParent = fs::parent($this->gameParams['originalFile']);
                if (str::contains($path,$origParent))
                {
                    uiLater(function (){UXDialog::show(Localization::getByCode('NEWGAMECONFIG.CLEANSKIPPED'),'WARNING');});
                    return;
                }
                
                new Process(['rm','-rf',$origParent])->start();
            }
        })->start();
    }

    /**
     * @event cleanAfterAdd.construct 
     */
    function doCleanAfterAddConstruct(UXEvent $e = null)
    {
        $switch = new UXToggleSwitch;
        $switch->selected = true;
        
        quUI::generateSetButton($e->sender,Localization::getByCode('NEWGAMECONFIG.CLEANAFTERADD'),$switch);
    }

    /**
     * @event label.construct 
     */
    function doLabelConstruct(UXEvent $e = null)
    {    
        $e->sender->text = Localization::getByCode('NEWGAMECONFIG.HEADER');
    }

    /**
     * @event label3.construct 
     */
    function doLabel3Construct(UXEvent $e = null)
    {    
        $e->sender->text = Localization::getByCode('NEWGAMECONFIG.NAME');
    }

    /**
     * @event label4.construct 
     */
    function doLabel4Construct(UXEvent $e = null)
    {    
        $e->sender->text = Localization::getByCode('NEWGAMECONFIG.GAMEPATH');
    }

    /**
     * @event label7.construct 
     */
    function doLabel7Construct(UXEvent $e = null)
    {    
        $e->sender->text = Localization::getByCode('NEWGAMECONFIG.PREFIXPATH');
    }

    /**
     * @event cancelButton.construct 
     */
    function doCancelButtonConstruct(UXEvent $e = null)
    {    
        $e->sender->text = Localization::getByCode('CANCEL');
    }

    /**
     * @event mainSelectLabel.construct 
     */
    function doMainSelectLabelConstruct(UXEvent $e = null)
    {    
        $e->sender->text = Localization::getByCode('NEWGAMECONFIG.SELECTMAIN');
    }

    /**
     * @event selectFileButton.construct 
     */
    function doSelectFileButtonConstruct(UXEvent $e = null)
    {    
        $e->sender->text = Localization::getByCode('NEXT');
    }

    /**
     * @event addGame.construct 
     */
    function doAddGameConstruct(UXEvent $e = null)
    {    
        $e->sender->text = Localization::getByCode('ADD');
    }

    
    function prepareForGame($files,$path = null)
    {
        $this->gameParams['path'] = $path;
        if ($path == null) # if $path is == null, then the files are parsed from rar
            $this->gameParams['unpackedPath'] = newGameConfigurator::detectBasePath($files);
            
        if ($this->gameParams['openedFromAria'])
        {
            $this->cleanAfterAdd->enabled = false;
            $this->cleanAfterAdd->data('quUIElement')->selected = true;
        }
            
        $candidates = $candidatesNames = [];
        foreach ($files as $file)
        {
            if (newGameConfigurator::checkAreCanListed($file) == false)
                continue;
                
            if (fs::ext($file) == 'rar')
            {
                if (fs::name(fs::parent($file)) == 'Fix Repair') {continue;}
                if (str::contains($file,'.part'))
                {
                    $noPart = str::sub($file,0,str::lastPos($file,'.part')).'.rar';
                    
                    if (arr::has($candidatesNames,fs::name($noPart)) == false)
                        $file = $noPart;
                    else 
                        continue;
                }
            }
            
            $fileName = fs::name($file);
            $candidates[] = [$fileName,str::replace(fs::parent($file),$path,null).'/',newGameConfigurator::checkAreInstallPossible($file)];
            $candidatesNames[] = $fileName;
            
            if (newGameConfigurator::checkAreAutoSelectPossible($file,$this->gameParams['originalFile'] != null ? $files : null))
            {
                $this->doSelectFileButtonAction(null,arr::last($candidates));
                return;
            }
        }
        
        $candidatesCount = arr::count($candidates);
        if ($candidatesCount == 0)
        {
            UXDialog::show(Localization::getByCode('NOTHING.FOUND'),'ERROR');
            
            $this->hide();
            return;
        }
        elseif ($candidatesCount == 1)
        {
            Logger::info('Only one file, so auto-select');
            
            $this->doSelectFileButtonAction(null,$candidates[0]);
        }
        else
        {
            $this->listView->items->clear();
            $this->listView->items->addAll($candidates);
        }
    }
    
    private function checkAreAddPossible()
    {
        if ($this->gameName->text == null or $this->gamePath->text == null or $this->prefixPath->text == null)
            return Localization::getByCode('NEWGAMECONFIG.FIELDSEMPTY');
        elseif ($this->appModule()->games->section($this->gameName->text) != [])
            return Localization::getByCode('MAINFORM.GAMEEXISTS');
        else 
            return true;
    }
    
    static function detectBasePath($files)
    {
        $candidate = [];
        foreach ($files as $file)
        {
            $parent = fs::parent($file);
            $count = str::count($parent,'/');
            if ($count < $candidate['count'] or $candidate == [])
                $candidate = ['path'=>$parent,'count'=>$count];
        }
        
        return $candidate['path'];
    }
    
    static private function isFile($file,$files = null) #wrapper with rar support
    {
        foreach ($files as $c => $f)
            $files[$c] = str::lower($f);
            
        return $files != null ? arr::has($files,$file) : fs::isFile($file);
    }
    
    static function checkAreAutoSelectPossible($file,$rarFiles = null)
    {
        $allowNames = ['eosauthlauncher.exe',
                       'launcher.exe'];
        $checks = 
        [
            'launcher.exe'=>function () use ($file,$rarFiles)
                {
                    if (self::isFile(str::lower(fs::parent($file).'/onlinefix.json'),$rarFiles)) {return true;}
                }
        ];
        
        $fileName = str::lower(fs::name($file));
        if (in_array($fileName,$allowNames))
        {
            if ($checks[$fileName] != null)
                return $checks[$fileName]();
            
            return true;
        }
    }
    
    static function checkAreCanListed($file)
    {
        $file = fs::name($file);
        $skipList = 
        [
            // Visual C++ redistributables
            '^vcredist.*\.exe$',
            '^vc_redist.*\.exe$',
        
            // DirectX
            '^dxsetup\.exe$',
            '^directx.*setup.*\.exe$',
            '^dxwebsetup\.exe$',
        
            // .NET
            '^dotnet.*setup.*\.exe$',
            '^ndp[A-Za-z0-9._-]*(?=.*-KB\d+)(?=.*-(?:x86|x64))(?=.*-AllOS)(?=.*-ENU)[A-Za-z0-9._-]*\.exe$',
        
            // Installers / uninstallers
            '^setup.*\.exe$',
            '^install.*\.exe$',
            '^uninstall.*\.exe$',
            '^unins[0-9]+\.exe$',
        
            // updaters / patchers
            '^updater.*\.exe$',
            '^patch.*\.exe$',
        
            // DRM / Shops
            '^steam.exe$',
            '^origin.*\.exe$',
            '^uplay.*\.exe$',
            '^epicgames.*\.exe$',
            '^gog.*\.exe$',
        
            // Unity engine stuff
            '^unitycrashhandler.*\.exe$',
            '^unitybugreporter.*\.exe$',
            '^winpixeventruntime.*\.exe$',
            '^monodistribution.*\.exe$',
        
            // Unreal engine stuff
            '^ue4.*prereq.*\.exe$',
            '^crashreportclient.*\.exe$',
            '^bugreporter.*\.exe$',
        
            // CryEngine / Lumberyard / Maybe others..
            '^sandbox.*\.exe$',
            '^editor.*\.exe$',
        
            // Additional tools
            '^config.*\.exe$',
            '^settings.*\.exe$',
            '^options.*\.exe$',
            '^benchmark.*\.exe$',
            '^profil(er|ing).*\.exe$',
            '^crashpad_handler.exe$',
            
            // Non-executables and rar
            '^(?!.*\.(exe|vbs|bat|rar)$)'
        ];
        
        foreach ($skipList as $reg)
        {
            if (Regex::match($reg,$file,Regex::CASE_INSENSITIVE))
                return false;
        }
        
        return true;
    }
    
    static function checkAreInstallPossible($file)
    {
        $allowExts = ['rar']; #maybe more in future lol
        
        return in_array(fs::ext($file),$allowExts);
    }
}
