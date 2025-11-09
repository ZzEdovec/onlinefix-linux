<?php
namespace app\modules;

use std, gui, app;

class ftpInstaller 
{
    static function install($gameName,$installers,$prefixPath,$installPath,$removeAfterInstall)
    {
        $panel = app()->form('MainForm')->addStubGame();
        $panel['gameName']->text = $gameName;
        $panel['status']->text = Localization::getByCode('INSTALLING');
        
        $proton = FilesWorker::getProtonExecutable();
        $installPathConverted = self::convertToWindowsPath($installPath);
        
        $GLOBALS['implicitDisableReason'] = 'ftpInstalling';
        UXApplication::setImplicitExit(false);
        
        new Thread(function () use ($panel,$proton,$installPath,$installPathConverted,$installers,$prefixPath,$removeAfterInstall,$gameName)
        {
            foreach ($installers as $installer)
            {
                fs::ensureParent($prefixPath);
                fs::makeDir($prefixPath);
                
                $installerProc = new Process([$proton,"run",$installer,"/DIR=$installPathConverted","/TASKS=","/SILENT"],fs::parent($installer),["STEAM_COMPAT_DATA_PATH"=>$prefixPath,
                                                                                                                                                 "STEAM_COMPAT_CLIENT_INSTALL_PATH"=>System::getProperty("user.home")."/.steam/steam",
                                                                                                                                                 "WINEDEBUG"=>"-all"])->start();
                FilesWorker::hookProcessOuts($installerProc);
                
                $exitCode = $installerProc->getExitValue();
                if ($exitCode != 0)
                {
                    uiLater(function () use ($exitCode,$panel)
                    {
                        UXDialog::show(sprintf(Localization::getByCode('FTPINSTALLER.FAILED'),$exitCode),"ERROR");
                        $panel['box']->free();
                    });
                    
                    new Process(['rm','-rf',$prefixPath])->start();
                    
                    if ($GLOBALS['implicitDisableReason'] == 'ftpInstalling')
                        UXApplication::setImplicitExit(true);
                        
                    return;
                }
            }
            
            $files = addGame::scanDir($installPath);   
            uiLater(function () use ($files,$installPath,$gameName,$prefixPath,$panel,$installers,$removeAfterInstall)
            {
                $panel['box']->free();
                
                $form = quUI::showFormAndFocus('newGameConfigurator',true);
                uiLater(function () use ($files,$installPath,$form,$gameName,$prefixPath,$installers,$removeAfterInstall)
                {
                    $form->gameParams['skipConfig'] = true;
                    $form->gameParams['originalFile'] = $installers[0];
                    $form->cleanAfterAdd->data('quUIElement')->selected = $removeAfterInstall;
                    $form->gameName->text = $gameName;
                    $form->prefixPath->text = $prefixPath;
                    
                    $form->prepareForGame($files,$installPath);
                    
                    if ($GLOBALS['implicitDisableReason'] == 'ftpInstalling')
                        UXApplication::setImplicitExit(true);
                });
            });
        })->start();
    }
    
    private static function convertToWindowsPath($path)
    {
        return 'Z:'.str::replace($path,'/','\\');
    }
}