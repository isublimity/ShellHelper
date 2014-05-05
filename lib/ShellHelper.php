<?php
final class ShellHelper
{
    const LONG_DAEMON='DAEMON';
    const MIN_WAIT='MIN_WAIT';

    private static $pid_name=null;
    private static $_iMakePid=false;
    private static $_pathPidFile='/tmp/';
    private static $_init=false;
    private static $_ParallelCommands=array();
    private static $_arg=array();
    private static $_argInits=false;


    public static function getPidFileName()
    {
        if (self::$_init) throw new Exception("ShellHelper need init");
        if (strlen(self::$pid_name)<3) throw new Exception("ShellHelper , not set pid_name\n");
        return self::$_pathPidFile.'_lock_'.self::$pid_name.'.pid.tmp';
    }

    public static function getPid()
    {
        if (self::$_init) throw new Exception("ShellHelper need init");
        return self::$pid_name;
    }
    public static function maxExecutionTime($set=null)
    {
        if (self::$_init) throw new Exception("ShellHelper need init");
        if ($set)
        {
            set_time_limit($set);
            self::$maxTimeMins=$set/60;
        }
        return self::$maxTimeMins;

    }
    public static function setParallelCommands($list)
    {
        self::$_ParallelCommands=$list;
    }
    private static function _getClassFunctions($object,$reg='Command')
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
    static private function initArgv()
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
        if (!self::$pid_name) throw new Exception('Shell helper need init');
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

            break;
        }
        return $ret;
    }



    private static function _init_run()
    {
        if ( ShellHelper::check_Shell() )
        {
            $f_exit=true;
            if (self::get('wait'))
            {
                self::message("Can`t run pid exists : ".self::$pid_name." , try wait:");
                for($f=0;$f<200;$f++)
                {
                    sleep(5);
                    echo '.';
                    if (!ShellHelper::check_Shell() )
                    {
                        $f_exit=false;
                        self::message("Free!\n");
                        break;
                    }
                }
            }
            if ($f_exit)
            {
                if (!self::isCron())
                {
                    self::message("ShellPID:Can`t run pid exists  : ".self::$pid_name."\n");
                }
                return false;
            }
        }
        ShellHelper::start_Shell();
        return true;

    }

    public static function init($objectClass,$methods=array())
    {
//        static public function initClassRun($class,$ShellPID=null,$methods=array(),$config=array(),$pid_params=array())
//    {
        $shell_name=get_class($objectClass);



        if (sizeof(self::$_ParallelCommands))
        {
            foreach (self::$_ParallelCommands as $key)
            {
                $shell_name.='_'.$key.'_'.self::get($key,'na');
            }

        }
        self::$pid_name=$shell_name;



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
}