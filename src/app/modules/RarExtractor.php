<?php
namespace app\modules;

use gui;
use Throwable;
use framework;
use Exception;
use php\io\IOException;
use std;

class RarExtractor 
{
    private $stdErr;
    private $stdOut;
    
    function getRarContent($file, $password = null)
    {
        Logger::info("Trying to read $file");
        
        $files = $this->executeUnrar($file,$password);
        
        Logger::info("$file readed successfully!");
        return $files;
    }
    
    function unpackRar($file, $path, $password = null)
    {
        Logger::info("Trying to unpack $file to $path");
        
        $this->executeUnrar($file,$password,$path);
        
        Logger::info("$file successfully unpacked to $path");
    }
    
    private function executeUnrar($file,$password = null,$path = null)
    {
        $this->stdErr = $this->stdOut = null;
        
        $extractor = new Process([FilesWorker::getThirdParty('unrar'),is_null($password) ? "-p-" : "-p$password","-y",is_null($path) ? "lb" : "x",$file],$path)->start();
        
        $extractor->getInput()->eachLine(function ($l) {$this->stdOut[] = $l;});
        $extractor->getError()->eachLine(function ($l) {$this->stdErr .= "\n$l";});
        
        if (str::contains($this->stdErr,'Incorrect password for'))
        {
            if (is_null($password))
            {
                Logger::warn('Incorrect password. Current password is null, so trying with online-fix.me');
                return $this->executeUnrar($file,'online-fix.me',$path);
            }
            else 
            {
                Logger::error("Incorrect password for $file");
                throw new IOException('Incorrect password');
            }
        }
        elseif (str::contains($this->stdErr,'Cannot open'))
        {
            $partFile = str::replace($file,'.rar','.part');
            if (fs::isFile($file) == false and fs::isFile($partFile.'1.rar'))
            {
                Logger::info('Multipart archive detected');
                
                if (is_null($path))
                {
                    $files = [];
                    for ($part = 1; ; $part++) # Until next part is exists
                    {
                        if (fs::isFile("$partFile$part.rar"))
                        {
                            Logger::info("Trying to read $partFile$part.rar");
                            $files = array_merge($files,$this->executeUnrar("$partFile$part.rar",$password));
                        }
                        else 
                        {
                            Logger::info('Readed all parts');
                            break;
                        }
                    }
                    
                    return $files;
                }
                else
                    $this->executeUnrar("$partFile1.rar",$password,$path);
            }
            else 
                throw new Exception($this->stdErr);
        }
        
        if (is_null($path))
            return $this->stdOut;
    }
    
    function retryWithEnsureError($catchedMessage,$file,$path = null)
    {
        if ($catchedMessage == 'Incorrect password' and uiLaterAndWait(function (){return UXDialog::confirm(Localization::getByCode('RAREXTRACTOR.FAILDEFPASSWD'));}))
        {
            $password = uiLaterAndWait(function (){return UXDialog::input(Localization::getByCode('RAREXTRACTOR.PASSWD'));});
            if ($password == null)
                return;
            
            try 
            {
                if (is_null($path))
                    return $this->getRarContent($file,$password);
                else 
                {
                    $this->unpackRar($file,$path,$password);
                    return true;
                }
            }
            catch (Throwable $ex)
            {
                uiLater(function (){UXDialog::show(Localization::getByCode('RAREXTRACTOR.FAILPASSWD'),'ERROR');});
                return;
            }
        }
        else 
        {
            uiLater(function (){UXDialog::show($catchedMessage,'ERROR');});
            return;
        }
    }
}