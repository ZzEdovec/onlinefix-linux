<?php
namespace app\forms;

use php\gui\controlsfx\UXToggleSwitch;
use Throwable;
use php\io\IOException;
use std, gui, framework, app;


class newGameConfigurator extends AbstractForm
{

    $gameParams = [
        'openedFromAria' => false,
        'skipConfig' => false,
        'originalFile' => null,
        'path' => null,
        'unpackedPath' => null,
        'canInstall' => false,
        'mainFile' => null
    ];
    $cancelConfirmed = false;
    $addedGame = false;

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
     * @event prefixPath.click 
     */
    function doPrefixPathClick(UXMouseEvent $e = null)
    {    
        $dc = new UXDirectoryChooser;
        
        $prefixDir = $dc->showDialog($this);
        if ($prefixDir == null)
            return;
        elseif (File::of($prefixDir)->findFiles() != [])
            UXDialog::show($this->_('NEWGAMECONFIG.PREFIX.PATHNONEMPTY'),'WARNING');
        
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
        
        
        if ($this->ftpInstallerBox->enabled != true and $gameDir->find() != [])
        {
            UXDialog::show($this->_('NEWGAMECONFIG.PATH.NONEMPTY'),'ERROR');
            $this->doGamePathClick();
            
            return;
        }
        
        $this->gamePath->text = $gameDir;
    }
    
    /**
     * @event selectFileButton.action 
     */
    function doSelectFileButtonAction(UXEvent $e = null,$candidate = null)
    {    
        if (is_null($this->listView->selectedIndex) or $this->listView->selectedIndex < 0)
        {
            $this->toast($this->_('NOTHING.SELECTED'));
            return;
        }
        
        if ($candidate == null)
        {
            $canInstall = $this->listView->selectedItem[2] ?? false;
            $candidate = $this->gameParams['path'].$this->listView->selectedItem[1].$this->listView->selectedItem[0];
        }
        else
        {
            $canInstall = $candidate[2] ?? false;
            $candidate = $this->gameParams['path'].$candidate[1].$candidate[0];
        }
        
        $nameNoExt = fs::nameNoExt($candidate);
        $ext = fs::ext($candidate);
        
        $isLauncher = str::contains(str::lower($nameNoExt),'launcher');
        if ($canInstall and $ext == 'rar')
        {
            $this->gameParams['originalFile'] = $candidate;
            $this->parseFromRar($candidate);
            
            return;
        }
        
        $this->gameParams['mainFile'] = $candidate;
        $this->gameParams['canInstall'] = $canInstall;
        
        $defaultInstallPath = launcherSettings::getBasePathFor('installs');
        if ($canInstall == false or $ext != 'exe')
        {
            $this->gamePath->text = $this->gameParams['path'] ?? $defaultInstallPath;
            $this->ftpInstallerBox->enabled = false;
        }
        elseif ($defaultInstallPath != null and $ext == 'exe' and $canInstall)
            $this->gamePath->text = $defaultInstallPath.'/'.fs::name(fs::parent($candidate));
        
        if ($this->gameParams['skipConfig'] ?? false)
        {
            $this->doAddGameAction();
            return;
        }
        
        if ($ext == 'exe' and $isLauncher == false and $canInstall == false)
            $this->gameName->text = $nameNoExt;
        else 
        {
            $this->gameName->text = fs::name(fs::parent($candidate));
            $nameNoExt = $this->gameName->text;
        }
        
        if (($this->gameParams['path'] ?? null) != null and $this->ftpInstallerBox->enabled == false)
        {
            $this->gamePathBox->enabled = false;
            $this->cleanAfterAdd->enabled = $this->cleanAfterAdd->data('quUIElement')->selected = false;
        }
        
        $prefixPath = launcherSettings::getBasePathFor('prefixes');
        $this->prefixPath->text = "$prefixPath/$nameNoExt";
        
        $this->mainSelectBox->free();
        $this->gameParamsBox->show();
    }
    
    function parseFromRar($file)
    {
        $extractor = new RarExtractor;
        try 
        {
            $files = $extractor->getRarContent($file);
        }
        catch (Throwable $ex)
        {
            $files = $extractor->retryWithEnsureError($ex->getMessage(),$file);
            if ($files == null)
            {
                $this->cancelConfirmed = true;
                $this->hide();
                return;
            }
        }
        
        $parsedFiles = [];
        foreach ($files as $file)
            $parsedFiles[] = "/$file";
            
        if ($parsedFiles == [])
        {
            UXDialog::showAndWait($this->_('NEWGAMECONFIG.EMPTYRAR'),'ERROR');
            $this->cancelConfirmed = true;
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
        if ($this->gameParams['openedFromAria'] ?? false)
        {
            if (uiConfirm($this->_('NEWGAMECONFIG.ARESURE')) == false)
                return;
        }

        $this->cancelConfirmed = true;
        $this->hide();
    }

    /**
     * @event hide
     */
    function doHide(UXWindowEvent $e = null)
    {
        if ($this->isFree() or $this->addedGame)
            return;

        if (($this->gameParams['skipConfig'] ?? false) and $this->cancelConfirmed == false)
            $this->cancelConfirmed = true;

        if ($this->cancelConfirmed)
        {
            if ($this->gameParams['openedFromAria'] ?? false)
            {
                self::removeDirectoryIfSafe($this->gameParams['path'] ?? null, launcherSettings::getBasePathFor('downloads'));
            }
            elseif ($this->gameParams['skipConfig'] ?? false)
            {
                $prefix = $this->prefixPath->text;
                $prefixBase = launcherSettings::getBasePathFor('prefixes');
                self::removeDirectoryIfSafe($prefix, $prefixBase);
            }
        }

        $this->free();
    }

    static function removeDirectoryIfSafe($path, $base = null)
    {
        if ($path == null or str::length(str::trim($path)) == 0)
            return false;

        $path = fs::abs($path);
        if ($path == '/' or $path == System::getProperty('user.home') or fs::isDir($path) == false)
            return false;

        if ($base != null)
        {
            $base = fs::abs($base);
            if ($path == $base or str::startsWith($path, $base . '/') == false)
                return false;
        }

        new Process(['rm','-rf',$path])->start();
        return true;
    }

    /**
     * @event gameName.keyUp 
     */
    function doGameNameKeyUp(UXKeyEvent $e = null)
    {
        $installPathDefault = launcherSettings::getBasePathFor('installs');
        $prefixParent = fs::parent($this->prefixPath->text);
        $prefixDefault = launcherSettings::getBasePathFor('prefixes');
        
        if ($prefixParent == $prefixDefault)
            $this->prefixPath->text = $prefixParent.'/'.$e->sender->text;
        if (fs::isDir($this->gamePath->text) == false)
            $this->gamePath->text = $installPathDefault.'/'.$e->sender->text;
    }

    /**
     * @event addGame.action 
     */
    function doAddGameAction(UXEvent $e = null)
    {
        if (($this->gameParams['skipConfig'] ?? false) == false)
        {
            $isAddPossible = $this->checkAreAddPossible();
            if (is_bool($isAddPossible) == false or $isAddPossible != true)
            {
                UXDialog::show($isAddPossible,'ERROR');
                return;
            }
        }
        elseif ($this->appModule()->games->section($this->gameName->text) != [])
        {
            UXDialog::show($this->_('MAINFORM.GAMEEXISTS'),'ERROR');
            return;
        }
        
        $gameName = str::trim($this->gameName->text);
        $gamePath = $this->gamePath->text;
        $prefixPath = $this->prefixPath->text;
        $cleanAfterAdd = $this->cleanAfterAdd->data('quUIElement')->selected;
        $ftpInstallerPath = $this->ftpInstallerPath->text;
        $originalFile = $this->gameParams['originalFile'] ?? null;
        $mainFile = $this->gameParams['mainFile'] ?? null;
        $unpackedPath = $this->gameParams['unpackedPath'] ?? null;
        $canInstall = $this->gameParams['canInstall'] ?? false;
        $openedFromAria = $this->gameParams['openedFromAria'] ?? false;
        $sourcePath = $this->gameParams['path'] ?? null;

        $this->addedGame = true;
        $this->free();
        
        if ($canInstall)
        {
            ftpInstaller::install($gameName,
                                  [$mainFile,$ftpInstallerPath],
                                  $prefixPath,$gamePath,
                                  $cleanAfterAdd);
            return;
        }
        
        $box = app()->form('MainForm')->addStubGame();
        $box['gameName']->text = $gameName;
        
        new Thread(function () use ($box, $gameName, $gamePath, $prefixPath, $cleanAfterAdd, $originalFile, $mainFile, $unpackedPath, $openedFromAria, $sourcePath)
        {
            try
            {
                $path = null;
                $resolvedMainFile = $mainFile;

                if ($originalFile != null and fs::ext($originalFile) == 'rar')
                {
                    uiLater(function () use ($box){$box['status']->text = $this->_('NEWGAMECONFIG.UNPACKING');});

                    $extractor = new RarExtractor;
                    try
                    {
                        $extractor->unpackRar($originalFile,$gamePath);
                    } catch (Throwable $ex)
                    {
                        $result = $extractor->retryWithEnsureError($ex->getMessage(),$originalFile,$gamePath);
                        if ($result != true)
                        {
                            uiLater(function () use ($box, $openedFromAria, $sourcePath)
                            {
                                app()->form('MainForm')->removeStubGame($box['box']);
                                if ($openedFromAria)
                                    newGameConfigurator::removeDirectoryIfSafe($sourcePath, launcherSettings::getBasePathFor('downloads'));
                            });
                            return;
                        }
                    }
                }

                if (fs::isFile($resolvedMainFile) == false)
                    $resolvedMainFile = $gamePath.$resolvedMainFile;

                if ($unpackedPath != null)
                    $path = $gamePath . $unpackedPath;

                uiLater(function () use ($box){$box['status']->text = $this->_('NEWGAMECONFIG.DLLS');});

                $parsed = FixParser::parseDlls($path ?? $gamePath);
                $noFix = $parsed == null;
                if ($noFix)
                    $parsed = ['overrides'=>'', 'realAppId'=>null, 'fakeAppId'=>null, 'fixPath'=>null];

                $bannerPath = $appIcon = null;
                if (($parsed['fakeAppId'] ?? null) != null)
                    $this->appModule()->games->set('fakeSteamID',$parsed['fakeAppId'],$gameName);

                if (($parsed['realAppId'] ?? null) != null)
                {
                    $bannerPath = FixParser::parseBanner($parsed['realAppId']);
                    if (fs::isFile($bannerPath))
                        $this->appModule()->games->set('banner',$bannerPath,$gameName);
                    else
                        uiLater(function () use ($bannerPath){UXDialog::show($bannerPath,'ERROR');});

                    $this->appModule()->games->set('steamID',$parsed['realAppId'],$gameName);
                }

                uiLater(function () use ($box){$box['status']->text = $this->_('NEWGAMECONFIG.ICOEXTRACT');});

                try
                {
                    $appIcon = FixParser::parseIcon($resolvedMainFile);
                    if (fs::isFile($appIcon))
                        $this->appModule()->games->set('icon',$appIcon,$gameName);
                } catch (Throwable $ex)
                {
                    uiLater(function () use ($ex)
                    {
                        UXDialog::show(sprintf($this->_('MAINFORM.ICONPARSERERROR'),$ex->getMessage()),'ERROR');
                    });
                }

                uiLater(function () use ($box){$box['status']->text = $this->_('NEWGAMECONFIG.SETTINGS');});

                $defaultProton = $this->appModule()->launcher->get('defaultProton','User Settings') ?? 'GE-Proton Latest';
                $this->appModule()->games->put(['overrides'=>$parsed['overrides'],
                                                'executable'=>$resolvedMainFile,
                                                'mainPath'=>$path ?? $gamePath,
                                                'prefixPath'=>$prefixPath,
                                                'proton'=>$defaultProton,
                                                'steamRuntime'=>boolval(FilesWorker::findSteamRuntime($defaultProton)),
                                                'steamOverlay'=>true,
                                                'wined3d'=>$this->appModule()->launcher->get('gamesUsesWined3d','User Settings'),
                                                'nativeWayland'=>$this->appModule()->launcher->get('gamesUsesWayland','User Settings'),
                                                'fixPath'=>$parsed['fixPath']],$gameName);

                uiLater(function () use ($parsed,$bannerPath,$appIcon,$box,$gameName,$resolvedMainFile,$noFix)
                {
                    app()->form('MainForm')->removeStubGame($box['box']);
                    app()->form('MainForm')->addGame($gameName,$resolvedMainFile,$parsed['overrides'],$bannerPath,$appIcon);
                    if ($noFix)
                        UXDialog::show($this->_('NEWGAMECONFIG.NOFIX'),'WARNING');
                });

                if ($cleanAfterAdd and $originalFile != null)
                {
                    $origParent = $openedFromAria ? $sourcePath : fs::parent($originalFile);
                    $target = $path ?? $gamePath;
                    if ($target == $origParent or str::startsWith($target, $origParent . '/') or str::startsWith($origParent, $target . '/'))
                    {
                        uiLater(function (){UXDialog::show($this->_('NEWGAMECONFIG.CLEANSKIPPED'),'WARNING');});
                        return;
                    }

                    self::removeDirectoryIfSafe($origParent, $openedFromAria ? launcherSettings::getBasePathFor('downloads') : null);
                }
            }
            catch (Throwable $ex)
            {
                uiLater(function () use ($box, $openedFromAria, $sourcePath, $ex)
                {
                    app()->form('MainForm')->removeStubGame($box['box']);
                    if ($openedFromAria)
                        newGameConfigurator::removeDirectoryIfSafe($sourcePath, launcherSettings::getBasePathFor('downloads'));
                    UXDialog::show($this->_('ARIADOWNLOADER.DOWNLOADERROR') . "\n" . $ex->getMessage(), 'ERROR');
                });
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
        
        quUI::generateSetButton($e->sender,$this->_('NEWGAMECONFIG.CLEANAFTERADD'),$switch);
    }

    /**
     * @event label.construct 
     */
    function doLabelConstruct(UXEvent $e = null)
    {    
        $e->sender->text = $this->_('NEWGAMECONFIG.HEADER');
    }

    /**
     * @event label3.construct 
     */
    function doLabel3Construct(UXEvent $e = null)
    {    
        $e->sender->text = $this->_('NEWGAMECONFIG.NAME');
    }

    /**
     * @event label4.construct 
     */
    function doLabel4Construct(UXEvent $e = null)
    {    
        $e->sender->text = $this->_('NEWGAMECONFIG.GAMEPATH');
    }

    /**
     * @event label7.construct 
     */
    function doLabel7Construct(UXEvent $e = null)
    {    
        $e->sender->text = $this->_('NEWGAMECONFIG.PREFIXPATH');
    }

    /**
     * @event cancelButton.construct 
     */
    function doCancelButtonConstruct(UXEvent $e = null)
    {    
        $e->sender->text = $this->_('CANCEL');
    }

    /**
     * @event mainSelectLabel.construct 
     */
    function doMainSelectLabelConstruct(UXEvent $e = null)
    {    
        $e->sender->text = $this->_('NEWGAMECONFIG.SELECTMAIN');
    }

    /**
     * @event selectFileButton.construct 
     */
    function doSelectFileButtonConstruct(UXEvent $e = null)
    {    
        $e->sender->text = $this->_('NEXT');
    }

    /**
     * @event addGame.construct 
     */
    function doAddGameConstruct(UXEvent $e = null)
    {    
        $e->sender->text = $this->_('ADD');
    }



    /**
     * @event ftpInstallerPath.click 
     */
    function doFtpInstallerPathClick(UXMouseEvent $e = null)
    {
        $fc = new UXFileChooser;
        
        $fc->extensionFilters = [['extensions'=>['*.exe'],'description'=>$this->_('NEWGAMECONFIG.FREETP.FILECHOOSER')]];
        $installer = $fc->showOpenDialog($this);
        if ($installer == null)
            return;
            
        $this->ftpInstallerPath->text = $installer;
    }

    /**
     * @event ftpInstallerBox.click 
     */
    function doFtpInstallerBoxClick(UXMouseEvent $e = null)
    {
        $this->doFtpInstallerPathClick();
    }

    /**
     * @event labelAlt.construct 
     */
    function doLabelAltConstruct(UXEvent $e = null)
    {
        $e->sender->text = $this->_('NEWGAMECONFIG.FREETP');
    }

    /**
     * @event keyUp-Esc 
     */
    function doKeyUpEsc(UXKeyEvent $e = null)
    {    
        $this->doCancelButtonAction();
    }





    
    function prepareForGame($files,$path = null)
    {
        $this->gameParams['path'] = $path;
        if ($path == null) # if $path is == null, then the files are parsed from rar
            $this->gameParams['unpackedPath'] = newGameConfigurator::detectBasePath($files);
            
        if ($this->gameParams['openedFromAria'] ?? false)
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
            
            if (newGameConfigurator::checkAreAutoSelectPossible($file,($this->gameParams['originalFile'] ?? null) != null ? $files : null))
            {
                $this->doSelectFileButtonAction(null,arr::last($candidates));
                return;
            }
        }
        
        $candidatesCount = arr::count($candidates);
        if ($candidatesCount == 0)
        {
            UXDialog::show($this->_('NOTHING.FOUND'),'ERROR');
            
            $this->cancelConfirmed = true;
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
        if (str::length(str::trim($this->gameName->text)) == 0 or str::length(str::trim($this->gamePath->text)) == 0 or str::length(str::trim($this->prefixPath->text)) == 0/* or ($this->ftpInstallerBox->enabled and $this->ftpInstallerPath->text == null)*/)
            return $this->_('NEWGAMECONFIG.FIELDSEMPTY');
        elseif ($this->appModule()->games->section($this->gameName->text) != [])
            return $this->_('MAINFORM.GAMEEXISTS');
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
            if ($candidate == [] or $count < $candidate['count'])
                $candidate = ['path'=>$parent,'count'=>$count];
        }
        
        return $candidate['path'];
    }
    
    static private function isFile($file,$files = null) #wrapper with rar support
    {
        if ($files == null)
            return fs::isFile($file);

        foreach ($files as $c => $f)
            $files[$c] = str::lower($f);
            
        return arr::has($files,$file);
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
            #'^setup.*\.exe$', commented because of ftp installers support lol
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
        switch (fs::ext($file))
        {
            case ('rar'):
                return true;
            break;
            case ('exe'):
                return File::of(fs::parent($file))->find(function ($d,$f){return fs::ext($f) == 'ftp';}) != [];
            break;
        }
        return false;
    }
}
