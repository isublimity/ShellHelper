<?
class LoadActions
{
    public function testCommand($load=null,$drop=false,$type=null)
    {
        echo "testCommand- OK!\n";
        echo "param: --load=$load\n";
        echo "param: --drop=$drop\n";
        echo "param: --type=$type\n";
    }
}
include_once 'lib/ShellHelper.php';

$parallelCommands=array('state','mod','new');

ShellHelper::setParallelCommands(array('state','mod','new'));
// -------------------------------------------------------
//ShellHelper::maxExecutionTime($maxExecutionTime);
// -------------------------------------------------------
ShellHelper::init(
    new LoadActions()
);

