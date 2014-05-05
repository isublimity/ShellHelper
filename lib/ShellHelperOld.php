<?php
//
// --------------------------------------------------------------------------------------------------------------------------
final class ShellHelper
{
    const LONG_DAEMON='DAEMON';
    const MIN_WAIT='MIN_WAIT';
    private static $maxTimeMins=-1;//13*60;
    private static $items=array();
    private static $_ShellHelper_Destory=null;
    private static $pid_name=null;
    private static $_cron=null;
    private static $_path=__DIR__;
    private static $_iMakePid=false;
    private static $_pidListCommands=array();
    public static function isCron()
    {
        return  self::$_cron;
    }
    public static function maxExecutionTime($set=null)
    {

        if ($set)
        {
            set_time_limit($set);
            self::$maxTimeMins=$set/60;
        }
        return self::$maxTimeMins;

    }
    public static function getPID()
    {
        return self::$pid_name;
    }
    public static function thisCron($flag=true,$silent=false)
    {
        self::$_cron=$flag;

    }
    public static function getPidFileName()
    {
        if (strlen(self::$pid_name)<3) die("ShellHelper , classPID\n");
        return '/tmp/_lock_'.self::$pid_name.'.pid.tmp';
    }
    public static function stopShell()
    {
        if (self::$_iMakePid)  @unlink(self::getPidFileName());
    }
    public static function startShell()
    {
        if (!file_put_contents(self::getPidFileName(),getmypid()))
        {
            throw new Exception('error : ShellHelper , cant file_put_contents ! in '.self::getPidFileName());
        }
        self::$_iMakePid=true;
    }
    public static function checkShell()
    {
//        if ($maxTimeMins>2) self::$maxTimeMins=$maxTimeMins;
        if (!self::$pid_name) die('Shell helper need init');
        //if (self::is("killpid"))
        $f=self::getPidFileName();


        clearstatcache(true,$f);// drop cache

        $m=@filemtime($f);
        if ($m===false) return false;
        // -----------------------------------
        $pid=file_get_contents($f);
        if ($pid===FALSE) return false;
        if (function_exists('posix_getsid'))
        {
            $sid=posix_getsid($pid);
            $gid=posix_getpgid($pid);
        }
        else $sid=1000;
        // -----------------------------------
        if ($sid==false)
        {
            self::echoRed("!Process not exist,not find by pid!\n");
            self::stopShell();
            return false;
        }
        // -----------// -----------
        $diff=(time()-$m)/60;
        if (self::$maxTimeMins>0)
        {
            if ($diff>self::$maxTimeMins)
            {

                self::echoRed("!!Long Process PID : $pid [$sid] ,  diff times: $diff \n");
                echo "> try kill : $pid \n";

                if (posix_kill($pid,9))
                {
                    echo "> posix kill : $pid say ok \n";
                    sleep(10);
                    $sid=posix_getsid($pid);
                    echo "> get sid : result : ".intval($sid)." for pid : $pid\n";
                    // ---------------------------------------------
                    if ($sid<1)
                    {
                        self::echoRed("!! Kill OK !!\n");
                        self::stopShell();
                        return false;
                    }
                    else
                    {
                        self::echoRed("WFT? try restart ? \n");
                    }
                }
                else
                {
                    self::echoRed("Posix cant kill\n");
                }







            }
        }

        return true;

    }
    private static $_init=false;
    private static $_arg=array();
    private static $_argInits=false;

    static private function parseArgs($argv)
    {
        array_shift($argv); $o = array();
        foreach ($argv as $a){
            if (substr($a,0,2) == '--'){ $eq = strpos($a,'=');
                if ($eq !== false){ $o[substr($a,2,$eq-2)] = substr($a,$eq+1); }
                else { $k = substr($a,2); if (!isset($o[$k])){ $o[$k] = true; } } }
            else if (substr($a,0,1) == '-'){
                if (substr($a,2,1) == '='){ $o[substr($a,1,1)] = substr($a,3); }
                else { foreach (str_split(substr($a,1)) as $k){ if (!isset($o[$k])){ $o[$k] = true; } } } }
            else { $o[] = $a; } }
        return $o;
    }
    static public function initRun($pid_name,$pidFromCommands=array())
    {

        ShellHelper::init($pid_name,$pidFromCommands);
        if ( ShellHelper::checkShell() )
        {
            $f_exit=true;
            if (self::get('wait'))
            {
                self::echoRed("Can`t run pid exists : ".self::$pid_name." , try wait:");
                for($f=0;$f<200;$f++)
                {
                    sleep(5);
                    echo '.';
                    if (!ShellHelper::checkShell() )
                    {
                        $f_exit=false;
                        self::echoRed("Free!\n");
                        break;
                    }
                }
            }
            if ($f_exit)
            {
                if (!self::isCron())
                {
                    self::echoRed("ShellPID:Can`t run pid exists  : ".self::$pid_name."\n");
                }
                exit(1);
            }
        }
        ShellHelper::startShell();

    }
    static public function setPidCommands($commandsArray)
    {
        self::$_pidListCommands=array_merge($commandsArray,self::$_pidListCommands);

    }
    static public function init($pid_name=null,$pidFromCommands=array())
    {
        if ($pid_name)
        {

            if (sizeof($pidFromCommands)) self::$_pidListCommands=$pidFromCommands;

            if (sizeof(self::$_pidListCommands))
            {
                foreach (self::$_pidListCommands as $key)
                {
                    $pid_name.='_'.$key.'_'.self::get($key,'na');
                }

            }
            self::$pid_name=$pid_name;
        }


        if (self::$_init) return true;
        self::$_ShellHelper_Destory=new ShellHelper_Destory();
        self::$_init=true;
        self::thisCron(self::get('cron',false),self::get('silent',false));
    }
    static public function is($name)
    {
        self::init();
        return isset(self::$_arg[$name]);
    }
    static public function echoRed($msg)
    {
        if (self::isCron())
        {
            echo $msg;
        }
        else
        {
            echo "\033[01;31m$msg\033[0m";
        }
        flush();
    }
    static public function dumpItems()
    {
        foreach (self::$items as $k=>$v)
        {
            $f=$v;
            if ($v!=='-') $f= "\033[01;33m$v\033[0m";;
            self::echoRed("\t$k".str_repeat(" ",10-strlen($k))." : ".$f."\n");
        }

    }
    static public function setConfig($config)
    {
        if (!is_array($config)) $config=array($config);
        if (is_array($config))
        {
            foreach ($config as $key=>$val)
            {
                $ret=(is_string($val)?$val:$key);
                switch( $ret)
                {
                    case self::LONG_DAEMON:
                        self::$maxTimeMins=-1;
                        break;
                }

            }
        }
    }
    static private function getClassFunctions($object,$reg='Command')
    {
        $out=array();
        $reflector = new ReflectionClass($object);
        $r= $reflector->getMethods(ReflectionMethod::IS_PUBLIC);
        foreach ($r as $p)
        {

            if (stripos($p->name,$reg)===false) continue;
            //Get the parameters of a method
            $d=str_ireplace($reg,'',$p->name);
            $out[$d]=array();
            $parameters = $reflector->getMethod($p->name)->getParameters();
            foreach ($parameters as $param)
            {
                $out[$d][]=$param->name;
            }
        }
        return $out;
    }
    static public function initClassRun($class,$ShellPID=null,$methods=array(),$config=array(),$pid_params=array())
    {
        if ($ShellPID==null)
        {
            $ShellPID=get_class($class);
        }

        self::setConfig($config);

        $ext_pid='';
        if (!sizeof($pid_params))
        {

            if ($methods)
                foreach ($methods as $key=>$method)
                {
                    $ext_pid.='_'.(is_array($method)?$key:$method)  .'-'.self::get((is_array($method)?$key:$method)  ,null);
                }

        }
        else
        {
            foreach ($pid_params as $key=>$method)
            {
                if (self::get((is_array($method)?$key:$method)))
                    $ext_pid.='_'.(is_array($method)?$key:$method)  .'-'.self::get((is_array($method)?$key:$method)  ,null);
            }
        }


        $ShellPID=$ShellPID.$ext_pid;

        self::initRun($ShellPID);
        // -----------------// -----------------// -----------------
        if (!self::$_init) die('must be init-shellhelper');
        $r=array(
            'setLimit'=>'limit',
            'setShift'=>'shift',
            'setLoop'=>'loop',
            'setIsCron'=>'cron',
            'setDebug'=>'debug',
            'setParams'=>null,
        );
        // ------------------------------------------------------------------------
        foreach ($r as $functName=>$nameParam)
        {
            if (method_exists($class,$functName))
            {
                $set=null;
                if ($functName=='setParams')
                {
                    $r=self::getAll();
                    $set=array($r);
                }
                else
                {
                    $set=array(self::get($nameParam));
                }


                call_user_func_array(array($class,$functName),$set);
            }
        }
        $listParamsForMethod=array();
        // ------------------------------------------------------------------------
        if (!sizeof($methods))
        {
            //auto create methods, get all "xyzCommand" functions -> --xyz
            //
            //
            $listParamsForMethod=self::getClassFunctions($class);
            $methods=array_keys($listParamsForMethod);

        }

        // ------------------------------------------------------------------------
        if (method_exists($class,'setIsCron'))
        {
            call_user_func_array(array($class,'setIsCron'),array(self::isCron()));
        }
        $result=null;
        $HelpShow='';
        foreach ($methods as $key=>$method)
        {
            if (is_array($method)) self::ifSetRun($key,$class,$method);
            else
            {
                $params=array();
                $addSuff=0;
                if (isset($listParamsForMethod[$method]))
                {
                    $addSuff=1;
                    $params=$listParamsForMethod[$method];

                }
                $HelpShow.=" --$method ".implode(',',$params)."\n";

                $result=self::ifSetRun($method,$class,$params,$addSuff);
            }
        }
        if ($result===null)
        {
            print_r("Result is null, try use commands \n");
            print_r($HelpShow);
        }
        return $result;
    }
    static public function ifSetRun($name,$class,$params=array(),$addSuff=false)
    {
        $name=ltrim($name,'--');
        $r=false;
        $val=self::get($name);
        if ($val)
        {
            $p=array();
            if (sizeof($params))
            {
                foreach ($params as $param)
                {
                    $p[]=self::get($param);
                }

            }

            if (!is_bool($val))
            {
                $p[]=$val;
            }
            if ($addSuff) $name=$name.'Command';
            $r=call_user_func_array(array($class,$name),$p);
        }
        return $r;
    }
    static public function initArgv()
    {
        if (self::$_argInits) return false;
        self::$_argInits=true;
        $argv=$GLOBALS['argv'];
        if (sizeof($argv)<1) return false;
        self::$_arg=self::parseArgs($argv);


    }
    static public function getLogFile()
    {
        return self::$pid_name.'.log';
    }
    static public function getAll()
    {
        self::initArgv();
        return self::$_arg;
    }
    static public function get($name,$ifNot=false)
    {
        self::initArgv();
        $name=str_ireplace('--','',$name);
        self::init();
        if (is_string($name))
        {
            $list=explode(',',$name);
        }
        else
        {
            $list=$name;
        }
        $ret=$ifNot;

        foreach ($list as $name)
        {
            if (isset(self::$_arg[$name]))
            {
                $ret=self::$_arg[$name];

            }

        }

        foreach ($list as $name)
        {
            $valueText=$ret;
            if ($ret===true) $valueText='true';
            if ($ret===false) $valueText='-';
            if ($ret===null) $valueText='null';

            self::$items[$name]=$valueText;
            break;
        }
        return $ret;
    }
}
