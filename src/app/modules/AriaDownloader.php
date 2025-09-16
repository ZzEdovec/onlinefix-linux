<?php
namespace app\modules;

use Throwable;
use Exception;
use app;
use php\net\websocket\WebSocketException;
use framework;
use facade\Json;
use php\net\websocket\WebSocket;
use gui;
use php\desktop\TrayIcon;
use php\desktop\SystemTray;
use std;

class AriaDownloader 
{
    /**
     * @var Process|null
    */
    private $aria;
    /**
     * @var WebSocket
    */
    private $ariaRPC;
    /**
     * @var Timer|null
    */
    private $checkTimer;
    /**
     * @var TrayIcon|null
    */
    private $trayIcon;
    private $downloadDir;
    private $downloads;
    private $waitingIds;
    private $ariaRPCPort;
    private $connection;
    private $allowRetry;
    
    function __construct($dnDir,$port = null)
    {
        $this->connection = 'classInited';
        
        if ($this->downloadDir == null)
            $this->downloadDir = $dnDir;
        
        fs::ensureParent($dnDir);
        fs::makeDir($dnDir);
        
        if (fs::isDir($dnDir) == false)
            uiLater(function (){UXDialog::show(Localization::getByCode('ARIADOWNLOADER.DOWNLOADFOLDER.NOTEXIST'));});
        
        if ($port == null)
        {
            $port = ServerSocket::findAvailableLocalPort();
            $this->aria = new Process(['aria2c','-V','--seed-time=0','--console-log-level=error','--enable-rpc=true',"--rpc-listen-port=$port"],$this->downloadDir);
        }
        else 
            $this->allowRetry = true;
        
        $this->ariaRPCPort = $port;
        $this->ariaRPC = new WebSocket;
        
        $this->ariaRPC->connectionTimeout = 5000;
        $this->ariaRPC->url = "ws://localhost:$port/jsonrpc";
        
        $this->ariaRPC->on('textMessage',function ($msg)
        {
            $msg = Json::decode($msg);
            
            if ($msg['error'] != null)
            {
                Logger::error('Aria error - '.$msg['error']['message']);
                return;
            }
            
            $method = str::replace($msg['method'],'aria2.',null);
            
            if (isset($this->waitingIds[$msg['id']]))
                $this->waitingIds[$msg['id']]($msg['result']);
            elseif ($msg['id'] == 'OFLL')
                return;
            elseif ($method != null)
            {
                if (method_exists($this,$method))
                {
                    Logger::info("Calling $method method as callback for Aria event");
                    $this->$method($msg);
                }
            }
        });
        $this->ariaRPC->on('connected',function ()
        {
            $this->connection = 'connected';
            
            Logger::info('Connected to Aria RPC!');
        });
        $this->ariaRPC->on('disconnected',function ()
        {
            $this->checkTimer->cancel();
            
            if ($GLOBALS['implicitDisableReason'] == 'downloading')
                UXApplication::setImplicitExit(true);
            
            app()->appModule()->launcher->remove('ariaPort','Downloads');
            app()->appModule()->launcher->remove('ariaDownloads','Downloads');
            
            $this->connection == 'classInited';
            
            if ($this->trayIcon != null)
            {
                SystemTray::remove($this->trayIcon);
                unset($this->trayIcon);
            }
            
            foreach ($this->downloads as $dn)
            {
                uiLater(function () use ($dn){app()->form('MainForm')->removeStubGame($dn['box']['box']);});
                $dn['timer']->cancel();
            }
            unset($this->downloads);
            unset($this->checkTimer);
            
            if (isset($this->ariaRPCPort))
                uiLater(function (){UXDialog::show(Localization::getByCode('ARIADOWNLOADER.ARIA.UNEXPECTED'),'ERROR');});
            else
                Logger::info('WebSocket disconnected, resources cleaned');
        });
    }
    
    function run($skipAriaStart = false)
    {
        new Thread(function () use ($skipAriaStart)
        {
            if ($this->aria != null and $skipAriaStart == false)
                $this->aria = $this->aria->start();
            
            try{$this->ariaRPC->connect();} 
            catch (WebSocketException $ex){
                if (str::contains($ex->getMessage(),'Connection refused') and $this->allowRetry)
                {
                    $this->allowRetry = false;
                    $this->ariaRPC->disconnect();
                    
                    $this->__construct($this->downloadDir);
                    $this->run();
                    
                    return true;
                }
                elseif (str::contains($ex->getMessage(),'Connection refused') and $skipAriaStart == false)
                {
                    $retry = 1;
                    
                    $this->ariaRPC->disconnect();
                    
                    while ($retry <= 10)
                    {
                        wait(1000);
                     
                        Logger::warn("Failed to connect, retry ($retry)");
                           
                        $this->__construct($this->downloadDir,$this->ariaRPCPort);
                        $run = $this->run(true);
                        if ($run == false)
                            $retry++;
                        else 
                            break;
                    }
                    
                    if ($retry == 11)
                        $this->connection = 'failed';
                    else
                        return true;
                }
                else 
                {
                    if ($skipAriaStart == false)
                    {
                        uiLater(function () use ($ex){UXDialog::show($ex,'ERROR');});
                        $this->connection = 'failed';
                    }
                    
                    Logger::error('Failed to connect to Aria RPC');
                    return;
                }
            }
            
            if ($this->trayIcon == null and SystemTray::isSupported())
            {
                $this->trayIcon = new TrayIcon(new UXImage('res://.data/img/oflogo.png'));
                
                $this->trayIcon->tooltip = 'OFLL Launcher (Game downloading)';
                $this->trayIcon->imageAutoSize = true;
                $this->trayIcon->on('click',function (){app()->showForm('MainForm');});
                
                SystemTray::add($this->trayIcon);
                
                Logger::info('Tray icon created!');
            }
            
            if ($this->aria != null)
            {
                $this->aria->getInput()->eachLine(function ($l){echo "$l\n";});
                
                $exitValue = $this->aria->getExitValue();
                if ($exitValue != 0)
                    uiLater(function () use ($exitValue){UXDialog::show(Localization::getByCode("ARIA.EXITCODE.$exitValue"),'ERROR');});
            }
        })->start();
        
        Logger::info('Waiting for Aria RPC');
        while ($this->connection == 'classInited') #block thread until connected or failed
            wait(500);
        
        if ($this->connection == 'failed')
        {
            if ($GLOBALS['implicitDisableReason'] == 'downloading')
                UXApplication::setImplicitExit(true);
            throw new Exception;
        }
        
        if ($this->allowRetry == false)
            app()->appModule()->launcher->set('ariaPort',$this->ariaRPCPort,'Downloads');
        
        return true;
    }
    
    private function sendRequest($method,$params = null,$id = null)
    {
        $request = ['jsonrpc'=>'2.0',
                    'id'=>$id ?? 'OFLL',
                    'method'=>$method,
                    'params'=>$params];
                    
        Logger::info("Sending $method to Aria");
        $this->ariaRPC->sendText(Json::encode($request));
    }
    
    private function sendRequestWithCallback($method,$callback,$params = null)
    {
        $id = str::random();
        while ($this->waitingIds[$id] != null)
            $id = str::random();
        
        $this->waitingIds[$id] = $callback;
        $this->sendRequest($method,$params,$id);
    }
    
    private function onDownloadStart($params)
    {
        $gid = is_array($params) ? $params['params'][0]['gid'] : $params;
        
        if ($this->downloads[$gid]['status'] == 'paused')
        {
            $this->downloads[$gid]['status'] = 'active';
            return;
        }
        
        $box = uiLaterAndWait(function () use ($gid)
        {
            $box = app()->form('MainForm')->addStubGame();
            $box['status']->text = Localization::getByCode('ARIADOWNLOADER.STARTDOWNLOAD');
            
            $menu = new UXContextMenu;
            
            $pause = new UXMenuItem;
            $cancel = new UXMenuItem(Localization::getByCode('ARIADOWNLOADER.CANCEL'));
            
            $pause->on('action',function () use ($gid)
            {
                if ($this->downloads[$gid]['status'] != 'paused')
                    $this->sendRequest('aria2.pause',[$gid]);
                else 
                    $this->sendRequest('aria2.unpause',[$gid]);
            });
            $cancel->on('action',function () use ($gid){$this->sendRequest('aria2.remove',[$gid]);});
            
            $menu->items->addAll([$pause,UXMenuItem::createSeparator(),$cancel]);
            
            $box['box']->on('click',function (UXMouseEvent $e) use ($gid,$pause,$menu)
            {
                $pause->text = $this->downloads[$gid]['status'] == 'paused' ? Localization::getByCode('ARIADOWNLOADER.RESUME') : Localization::getByCode('ARIADOWNLOADER.PAUSE');
                
                $menu->showByNode($e->sender,$e->x,$e->y);
            });
            
            return $box;
        });
        
        $this->downloads[$gid]['box'] = $box;
        $this->downloads[$gid]['status'] = 'active';
        if (isset($this->checkTimer) == false)
        {
            $this->checkTimer = Timer::every('3s',function () use ($gid)
            {
                $checkFunc = function ($downloads)
                {
                    foreach ($downloads as $dn)
                        $this->updateDownloadStatus($dn);
                };
                
                $this->sendRequestWithCallback('aria2.tellActive',$checkFunc);
                $this->sendRequestWithCallback('aria2.tellWaiting',$checkFunc,[0,arr::count($this->downloads)]);
            });
        }
        
        if (is_array($params))
            $this->sendRequestWithCallback('aria2.tellStatus',[$this,'generateMagnet'],[$gid]);
    }
    
    private function onDownloadComplete($params)
    {
        $gid = is_array($params) ? $params['params'][0]['gid'] : $params;

        try{uiLaterAndWait(function () use ($gid){app()->form('MainForm')->removeStubGame($this->downloads[$gid]['box']['box']);});} catch (Throwable $ex){}
        
        unset($this->downloads[$gid]);
        $this->syncDownloadsConfig();
        
        if (is_array($params) == false)
        {
            if ($this->downloads == [])
            {
                unset($this->ariaRPCPort);
                $this->sendRequest('aria2.shutdown');
                $this->ariaRPC->disconnect();
            }
            
            return;
        }
        
        $this->sendRequestWithCallback('aria2.getFiles',function ($params) use ($gid)
        {
            if (str::contains($params[0]['path'],'[METADATA]') or str::contains($params[0]['path'],'[MEMORY]'))
                return;
            
            if ($this->downloads == [])
            {
                unset($this->ariaRPCPort);
                $this->sendRequest('aria2.shutdown');
                $this->ariaRPC->disconnect();
            }
            
            foreach ($params as $file)
            {
                if (fs::ext($file['path']) == 'rar')
                {
                    $dnPath = $this->downloadDir.'/'.str::split(str::replace($file['path'],$this->downloadDir.'/',null),'/')[0];
                    $files = addGame::scanDir($dnPath);

                    if ($files == [] or $files == null)
                        break;
                    
                    uiLater(function () use ($files,$dnPath)
                    {
                        $form = app()->showNewForm('newGameConfigurator');
                        $form->gameParams['openedFromAria'] = true;
                        uiLater(function () use ($files,$dnPath,$form) {$form->prepareForGame($files,$dnPath);});
                    });
                    
                    return;
                }
            }
            
            uiLater(function (){UXDialog::show(Localization::getByCode('ARIADOWNLOADER.NOVALIDFILES'),'ERROR');});
        },[$gid]);
    }
    
    private function onDownloadStop($event)
    {
        $this->sendRequestWithCallback('aria2.getFiles',function ($params) use ($event)
        {
            foreach ($params as $file)
            {
                if (str::contains($file['path'],'[METADATA]') or str::contains($file['path'],'[MEMORY]'))
                    continue;
                    
                new Process(['rm','-rf',fs::parent($file['path'])])->startAndWait();
            }
            
            $this->onDownloadComplete($event['params'][0]['gid']);
        },[$event['params'][0]['gid']]);
    }
    
    private function onDownloadPause($params)
    {
        $this->downloads[$params['params'][0]['gid']]['status'] = 'paused';
    }
    
    private function onDownloadError($params)
    {
        uiLater(function () use ($params){UXDialog::show(Localization::getByCode('ARIADOWNLOADER.DOWNLOADERROR'),'ERROR');});
        
        $this->onDownloadComplete($params['params'][0]['gid']);
    }
    
    private function updateDownloadStatus($params)
    {
        $gid = $params['gid'];
        
        if ($params['status'] == 'paused')
            uiLater(function () use ($gid)
            {
                $this->downloads[$gid]['box']['status']->text = Localization::getByCode('ARIADOWNLOADER.SUSPENDED'); 
                $this->downloads[$gid]['box']['status']->height = 16;
            });
        elseif ($params['status'] == 'waiting')
            uiLater(function () use ($gid)
            {
                $this->downloads[$gid]['box']['status']->text = Localization::getByCode('ARIADOWNLOADER.WAITING');
                $this->downloads[$gid]['box']['status']->height = 16;
            });
        elseif (str::contains($params['files'][0]['path'],'[METADATA]'))
        {
            uiLater(function () use ($params,$gid)
            {
                $this->downloads[$gid]['box']['gameName']->text = str::replace($params['files'][0]['path'],'[METADATA]',null);
                $this->downloads[$gid]['box']['status']->text = Localization::getByCode('ARIADOWNLOADER.FETCHINGMETADATA');
                $this->downloads[$gid]['box']['status']->height = 16;
            });
        }
        else 
        {
            $convertedCompleted = $this->formatBytes($params['completedLength']);
            $convertedTotal = $this->formatBytes($params['totalLength']);
            $convertedSpeed = $this->formatBytes($params['downloadSpeed']);
            $eta = $this->formatETA(($params['totalLength'] - $params['completedLength']) / $params['downloadSpeed']);
            
            uiLater(function () use ($params,$gid,$convertedCompleted,$convertedTotal,$convertedSpeed,$eta)
            {
                $this->downloads[$gid]['box']['gameName']->text = $params['bittorrent']['info']['name'];
                if ($params['downloadSpeed'] > 0)
                {
                    $this->downloads[$gid]['box']['status']->text = "ETA: $eta\n$convertedCompleted/$convertedTotal\n$convertedSpeed/s";
                    $this->downloads[$gid]['box']['status']->height = 56;
                }
                else 
                {
                    $this->downloads[$gid]['box']['status']->text = Localization::getByCode('ARIADOWNLOADER.IDLE');
                    $this->downloads[$gid]['box']['status']->height = 16;
                }
            });
        }
        
        $this->downloads[$gid]['status'] = $params['status'];
    }
    
    private function formatETA($seconds)
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;
    
        if ($hours > 0) {
            return sprintf("%dh %dm %ds", $hours, $minutes, $secs);
        } elseif ($minutes > 0) {
            return sprintf("%dm %ds", $minutes, $secs);
        } else {
            return sprintf("%ds", $secs);
        }
    }
    
    private function formatBytes($bytes)
    {
        if ($bytes >= 1073741824)
        {
            $converted = $bytes / 1073741824; # 1073741824 = 1 GB
            return round($converted,1).' GB';
        }
        elseif ($bytes >= 1048576)
        {
            $converted = $bytes / 1048576;
            return round($converted,1).' MB';
        }
        elseif ($bytes >= 1024)
        {
            $converted = $bytes / 1024;
            return round($converted,1).' KB';
        }
        else 
            return $bytes;
    }
    
    private function syncDownloadsConfig()
    {
        if ($this->downloads == [] or isset($this->downloads) == false)
        {
            app()->appModule()->launcher->remove('ariaDownloads','Downloads');
            return;
        }
        
        foreach ($this->downloads as $gid => $payload)
        {
            if ($toWrite != null)
                $toWrite .= '\\\\\\\\';
            
            $toWrite .= "$gid====".$payload['magnet'];
        }
        
        app()->appModule()->launcher->set('ariaDownloads',$toWrite,'Downloads');
    }
    
    private function readDownloadsConfig()
    {
        $downloads = app()->appModule()->launcher->get('ariaDownloads','Downloads');
        if (str::contains($downloads,'\\\\\\\\') == false)
        {
            $parsedEnv = str::split($downloads,'====');
            return [$parsedEnv[0] => $parsedEnv[1]];
        }
        else
        {
            foreach (str::split($downloads,'\\\\\\\\') as $download)
            {
                $download = str::split($download,'====');
                $parsedEnv[$download[0]] = $download[1];
            }
            
            return $parsedEnv;
        }
    }
    
    private function generateMagnet($params)
    {
        $baseUrl = 'magnet:?xt=urn:btih:'.$params['infoHash'].'&dn='.urlencode($params['bittorrent']['info']['name'] ?? $this->downloads[$params['gid']]['box']['gameName']->text);
        foreach ($params['bittorrent']['announceList'] as $ann)
        {
            foreach ($ann as $tracker)
            {
                $baseUrl .= '&tr='.urlencode($tracker);
            }
        }
        
        $this->downloads[$params['gid']]['magnet'] = $baseUrl;
        $this->syncDownloadsConfig();
    }
    
    function addDownload($magnet)
    {
        $GLOBALS['implicitDisableReason'] = 'downloading';
            UXApplication::setImplicitExit(false);
        
        $this->sendRequest('aria2.addUri',[[$magnet]]);
    }
    
    function isRunning()
    {
        return $this->connection == 'connected';
    }
    
    function reAddDownloadsFromPreviousSession()
    {
        $downloads = $this->readDownloadsConfig();
        
        $total = arr::count($downloads);
        $addFunc = function ($downloads)
        {
            foreach ($downloads as $dn)
                $this->onDownloadStart($dn['gid']);
        };
        
        $this->sendRequestWithCallback('aria2.tellActive',$addFunc);
        $this->sendRequestWithCallback('aria2.tellWaiting',$addFunc,[0,$total]);
        
        waitAsync('2.5s',function () use ($downloads)
        {
            foreach ($downloads as $gid => $magnet)
            {
                if (isset($this->downloads[$gid]) == false)
                    $this->addDownload($magnet);
            }
        });
    }
}