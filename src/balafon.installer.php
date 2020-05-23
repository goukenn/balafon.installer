<?php 

(PHP_OS=="WINNT") && define("WINNT", 1); 
(PHP_OS=="Darwin") && define("MACOS", 1); 
(PHP_OS=="Linux") && define("Linux", 1); 


class BalafonInstaller{
    var $dir;
	const DOWNLOAD_URI  = "http://local.com/balafon/download";
	const VERSION = "1.0";
    private function __BalafonInstaller(){
        
    }
	private function _get_apache_dir(){
		if (defined('WINNT')){
			$c = shell_exec("where httpd");
			if (!empty($c)){
				$apachedir = realpath(dirname($c)."/../");
				return $apachedir;
			}
		}
		return false;
	}
	private function _get_apache_dir_conf($apachedir){
		if (defined("WINNT"))
			return $apachedir."/conf/extra";
		else {
			return $apacchedir."/site-available";
		}
	}
    public static function Install($dir=null, $type = 1, $options=null){
		
        if (!extension_loaded("curl"))
            die("can't install : curl extension  required");
        
        if (!extension_loaded("zip"))
            die("can't install : zip extension required");
	if ($dir ===null)
		$dir=dirname(__FILE__);//m(
	
        if (!file_exists($dir))
            mkdir($dir);
        $c = new BalafonInstaller();
        $c->dir = $dir;

        echo "start installing ...\n";
        echo "get library ...";
        $data = $c->__curl(self::DOWNLOAD_URI, null, [
			"FOLLOWLOCATION"=>1
        ]); 
		echo "[OK]\n";
		$n = tempnam($dir, "lib");
		rename($n, $n = $n.".zip");
		if ($hopen = fopen($n, "w+")){
			fwrite($hopen, $data, strlen($data));
			fclose($hopen);
		}
		
		//file_put_contents($n , $data);
		
		echo "open archive \n";
		$rootdir=$dir;
		if ($type == 1)
			$dir.="/src/application";
		
		$hzip = zip_open($n);
		if ($hzip && is_resource($hzip)){ 
			$entries = []; 
			while ($e = zip_read($hzip)){
				$sn=zip_entry_name($e); 
				$entries[$sn] = $e; 
			}
			
			if (array_key_exists("__lib.def", $entries) &&
				array_key_exists("manifest.xml", $entries)){
				$entry = $entries["manifest.xml"];
				// $fsize = zip_entry_filesize($entry);
				// $content = zip_entry_read($entry, $fsize);
				unset($entries["__lib.def"]);
				unset($entries["manifest.xml"]);
				$c->_extract($entries, $dir); 
			}
			echo "close zip";
			zip_close($hzip);
			unset($hzip, $entries, $entry);
		} 
		unlink($n); 
		require_once($dir."/Lib/igk/igk_framework.php");
		if ($type==1){
			$pdir = $rootdir."/src/public";
		}
		
		//---
		IGKIO::CreateDir($pdir."/assets/js");
		IGKIO::CreateDir($pdir."/assets/css");
		IGKIO::CreateDir($pdir."/assets/img");
		IGKIO::CreateDir($pdir."/assets/library");
		IGKIO::CreateDir($pdir."/assets/values");
		IGKIO::CreateDir($pdir."/assets/lang");
		IGKIO::CreateDir($pdir."/assets/fonts");
		IGKIO::CreateDir($rootdir."/temp");
		IGKIO::CreateDir($rootdir."/logs");
		$sep = DIRECTORY_SEPARATOR;
		igk_io_w2file($pdir."/index.php", <<<EOF
<?php
\$apppath = realpath(dirname(__FILE__)."/../application");
define('IGK_PROJECT_DIR', \$apppath.'{$sep}Projects');
define('IGK_APP_DIR', \$apppath);
define('IGK_SESS_DIR', dirname(\$apppath).'{$sep}temp');
@require_once(\$apppath."/Lib/igk/igk_framework.php");  
unset(\$appath); 
igk_sys_render_index(__FILE__);
EOF
);
$startp =  realpath($dir);
		$options = (object)igk_array_extract($options, "apacheDir|Debug|port|serverName");
		if (empty($apacheDir = $options->apacheDir)){
			$apacheDir = $c->_get_apache_dir();
		}
	 
	
		if (!empty($apacheDir)){
			$confdir =	$c->_get_apache_dir_conf($apacheDir);
			
			$confile = $confdir."/httpd-vhosts-".$options->port.".conf";
			$environment = "development";
			$directory = realpath($pdir);
			$sslinfo = "";
			$e_logs = implode($sep,  [realpath($rootdir), "logs", "error_log.log"]);
			$a_logs = implode($sep,  [realpath($rootdir), "logs", "access_log.log"]);
			 
			
			igk_io_w2file($confile, <<<EOF
Listen {$options->port}

<VirtualHost *:{$options->port}>
SetEnv ENVIRONMENT {$environment}
ServerName {$options->serverName}
DocumentRoot "{$directory}"
ErrorLog "{$e_logs}"
CustomLog "{$a_logs}" common

<Directory {$directory}>
Options -Indexes +Includes +FollowSymlinks -MultiViews
Order deny,allow
AllowOverride None
Allow from all
Require all granted

<IfModule rewrite_module>
RewriteEngine on
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule (.)+ /index.php?_code=104 [QSA,L]
</IfModule>


</Directory>
{$sslinfo}
</VirtualHost>
EOF
);


$c->_gen_sym_link(
		$confile,	
		realpath($rootdir)."/httpd-{$options->port}.conf"
		);

	}
	
	
chown($pdir, "www-data:www-data");
chmod($pdir, "776");
		
@unlink($dir."/Data/configure");

		
    }
	private function _gen_sym_link($n, $f){
		if (is_link($f)){
			$c = 0; 
			if (is_dir($f)){ 
				rmdir($f);
			}else{ 
				$c = unlink($f); 
			} 
		} 
		$r = @symlink($n, $f);
		if ($r===false){
			echo ":-( link for : ".$f." ==> ".$n." failed \n"; 
		}
		return $r;
	}
	private function _createdir($dir, $mode=0775){
		$pdir=array($dir);
		$i=1;
		while($dirname=array_pop($pdir)){
			if(empty($dirname))
				return false;
			if(is_dir($dirname))
				continue;
			if(empty($dirname))
				return false;
			if(is_dir($dirname))
				continue;
			$p=dirname($dirname);
			if(empty($p))
				continue;
			if(is_dir($p)){
				@mkdir($dirname);
				chmod($dirname, $mode);
			}
			else{
				array_push($pdir, $dirname);
				array_push($pdir, dirname($dirname));
			}
			if($i > 10)
				break;
			$i++;
		}
		return count($pdir) == 0;
	}
	private function _extract($entries, $dir){
		foreach($entries as $n=>$entry){
			if (zip_entry_compressionmethod ($entry) =="stored"){
				continue; 
			}
			$ofile= $dir."/".$n;
			$odir = dirname($ofile);
			if (!is_dir($odir)){
				$this->_createdir($odir);
				
			}			
			$fsize = zip_entry_filesize($entry);
			$content = zip_entry_read($entry, $fsize);
			file_put_contents($ofile, $content);
			echo "extract : ".realpath($ofile)."\n";
		}
	}

    private function __curl($uri, $args=null, $method="GET",& $info=null){
 
	$r = curl_init();
	$f = dirname(__FILE__)."/cookies.txt";
	if (file_exists($f)){
		file_put_contents($f, "");
	}
	 $data=null;
	 $curlOptions = [];
	 if ($r){
		curl_setopt($r, CURLOPT_URL, $uri);
		curl_setopt($r, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($r, CURLOPT_RETURNTRANSFER, true);
		//+add cookie file
		
		curl_setopt($r, CURLOPT_COOKIEFILE, $f);
		curl_setopt($r, CURLOPT_COOKIEJAR, $f);
		if($args){
            curl_setopt($r, CURLOPT_POSTFIELDS, http_build_query($args));
        }
		if (is_string($method)){
			$curlOptions["POST"] = $method=="POST"? 1: "0";
		}else if (is_array($method)){
			$curlOptions = array_merge($curlOptions, $method);
		} 
		foreach($curlOptions as $k=>$v){
			curl_setopt($r, constant("CURLOPT_".$k), $v);
		}
		if (($data = curl_exec($r))===false){
			// igk_ilog(igk_ob_get_func("igk_wln", ["curl error ", curl_getinfo($r)]));
			echo "failed ...." . curl_getinfo($r , CURLINFO_HTTP_CODE);
		}	
			if ($info){
				//$code = curl_getinfo($r, CURLINFO_HTTP_CODE);			 
				foreach($info as $l=>$m){
					$info[$l] = curl_getinfo($r, constant("CURLINFO_".$l));			 
				}
			}
		
		curl_close($r); 
	 }
	 
	 return $data;
	}
 
	 public static function _show_usage($gcommand=null){
		 echo "Balafon Installer version : ".self::VERSION."\n";
		 echo "available command:\n";
		 foreach($gcommand as $k=>$v){
			 echo str_repeat(" ", 2)."\e[1;32m". str_pad($k, 30, " ")."\e[0m";
			 if (!is_callable($v)){
				$h = $v["help"]; 
				echo str_repeat(" ", 2). ": " .$h;
			 }
			 echo  "\n";
		 }
	 }
}


$gcommand = [
"--port"=>[
	"help"=>"setup default port",
	"callback"=>function($action, $o){
	if ($o->waitForNextEntryFlag){
	
		$o->defaultPort = $action;
		$o->waitForNextEntryFlag=0;
		return;
	}
	$o->waitForNextEntryFlag=1;
}],
"--server-name"=>[
	"help"=>"change the default server name",
	"callback"=>function($action, $o){
	if ($o->waitForNextEntryFlag){		
		$o->serverName = $action;
		$o->waitForNextEntryFlag=0;
		return;
	}
	$o->waitForNextEntryFlag=1;
}],
"--outdir"=>function($a, $o){
	if ($o->waitForNextEntryFlag){
	
		$o->outputDir = $a;
		$o->waitForNextEntryFlag=0;
		return;
	}
	$o->waitForNextEntryFlag=1;
},

"--help"=>function($n, $o){
	$o->exec = [BalafonInstaller::class, "_show_usage"];
},
"--debug"=>function($o){
	$o->debug = 1; 
},
"--apachedir"=>["callback"=>function($action, $o, $args=null){
	if ($o->waitForNextEntryFlag){
		if (is_dir($action))
			$o->apacheDir = $action;
		else die("directory doesn't exists");
		$o->waitForNextEntryFlag=0;
		return;
	}
	$o->waitForNextEntryFlag=1;
	
}, "help"=>"setup apache directory"],
"--version"=>[
	"callback"=>function($o){
		$o->exec = function(){
			echo "Balafon Installer : ".BalafonInstaller::VERSION."\n";
		};
}, "help"=>"show intaller version"]

];

$tab = array_slice($_SERVER['argv'], 1);
$command= (object)[
"waitForNextEntryFlag"=>0,
"port"=>8300,
"serverName"=>"localhost",
"outputDir"=>dirname(__FILE__),
"installtype"=>1,
"exec"=> function($a, $c){ 
		BalafonInstaller::Install($c->outputDir, $c->installtype, $c);
	}
];


 
	foreach($tab as $k=>$v){
		
		if ($command->waitForNextEntryFlag){
			$action($v, $command, []);
			$command->waitForNextEntryFlag = false;
		}
		$c = explode(":", $v);
		
		if (isset($gcommand[$c[0]]))
		{
			$action = $gcommand[$c[0]];
			if (is_array($action)){
				$action = $action["callback"];				
			}
			if (is_callable($action)) {
				$action($v, $command, implode(":", array_slice($c,1)));
			}
		}
	}

if (!$command->exec){
	BalafonInstaller::_show_usage($gcommand);
	exit;
}
$bind = $command->exec;
if (is_callable($bind)){
	$bind($gcommand, $command);
}else{
	BalafonInstaller::_show_usage($gcommand);
}
exit;
BalafonInstaller::Install(dirname(__FILE__)."/balafon", $cmd);
 
