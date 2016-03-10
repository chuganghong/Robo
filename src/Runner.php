<?php
namespace Robo;

use Robo\Common\IO;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;

class Runner
{
    use IO;

    const VERSION = '0.7.1';
    const ROBOCLASS = 'RoboFile';
    const ROBOFILE = 'RoboFile.php';

    /**
     * @var string PassThoughArgs
     */
    protected $passThroughArgs = null;

    /**
     * @var string RoboClass
     */
    protected $roboClass;

    /**
     * @var string RoboFile
     */
    protected $roboFile;

    /**
     * @var string working dir of Robo
     */
    protected $dir;

    /**
     * Class Constructor
     * @param null $roboClass
     * @param null $roboFile
     */
    public function __construct($roboClass = null, $roboFile = null)
    {
        // set the const as class properties to allow overwriting in child classes
        $this->roboClass = $roboClass ? $roboClass : self::ROBOCLASS ;
        $this->roboFile  = $roboFile ? $roboFile : self::ROBOFILE;
        $this->dir = getcwd();
    }


    protected function loadRoboFile()
    {
        if (class_exists($this->roboClass)) {
            return true;
        }

        if (!file_exists($this->dir)) {
            $this->yell("Path in `{$this->dir}` is invalid, please provide valid absolute path to load Robofile", 40, 'red');
            return false;
        }

        $this->dir = realpath($this->dir);
        chdir($this->dir);

        if (!file_exists($this->dir . DIRECTORY_SEPARATOR . $this->roboFile)) {
            return false;
        }

        require_once $this->dir . DIRECTORY_SEPARATOR .$this->roboFile;

        if (!class_exists($this->roboClass)) {
            $this->writeln("<error>Class ".$this->roboClass." was not loaded</error>");
            return false;
        }
        return true;
    }

    public function execute($input = null)
    {
        register_shutdown_function(array($this, 'shutdown'));
        set_error_handler(array($this, 'handleError'));
        Config::setOutput(new ConsoleOutput());

        $input = $this->prepareInput($input ? $input : $this->shebang($_SERVER['argv']));
        Config::setInput($input);
        $app = new Application('Robo', self::VERSION);

        if (!$this->loadRoboFile()) {
            $this->yell("Robo is not initialized here. Please run `robo init` to create a new RoboFile", 40, 'yellow');
            $app->addInitRoboFileCommand($this->roboFile, $this->roboClass);
            $app->run($input);
            return;
        }
        $app->addCommandsFromClass($this->roboClass, $this->passThroughArgs);
        $app->setAutoExit(false);
        return $app->run($input);
    }

    /**
     * Process a shebang script, if one was used to launch this Runner.
     *
     * @param array $args
     * @return $args with shebang script removed
     */
    protected function shebang($args)
    {
        // Option 1: Shebang line names Robo, but includes no parameters.
        // #!/bin/env robo
        // The robo class may contain multiple commands; the user may
        // select which one to run, or even get a list of commands or
        // run 'help' on any of the available commands as usual.
        if ($this->isShebangFile($args[1])) {
            return array_merge([$args[0]], array_slice($args, 2));
        }
        // Option 2: Shebang line stipulates which command to run.
        // #!/bin/env robo mycommand
        // The robo class must contain a public method named 'mycommand'.
        // This command will be executed every time.  Arguments and options
        // may be provided on the commandline as usual.
        if ($this->isShebangFile($args[2])) {
            return array_merge([$args[0]], explode(' ', $args[1]), array_slice($args, 3));
        }
        return $args;
    }

    /**
     * Determine if the specified argument is a path to a shebang script.
     * If so, load it.
     *
     * @param $filepath file to check
     * @return true if shebang script was processed
     */
    protected function isShebangFile($filepath)
    {
        if (!file_exists($filepath)) {
            return false;
        }
        $fp = fopen($filepath, "r");
        if ($fp === false) {
            return false;
        }
        $line = fgets($fp);
        $result = $this->isShebangLine($line);
        if ($result) {
            while ($line = fgets($fp)) {
                $line = trim($line);
                if ($line == '<?php') {
                    $script = stream_get_contents($fp);
                    if (preg_match('#^class *([^ ]+)#m', $script, $matches)) {
                        $this->roboClass = $matches[1];
                        eval($script);
                        $result = true;
                    }
                }
            }
        }
        fclose($fp);

        return $result;
    }

    /**
     * Test to see if the provided line is a robo 'shebang' line.
     */
    protected function isShebangLine($line)
    {
        return ((substr($line,0,2) == '#!') && (strstr($line, 'robo') !== FALSE));
    }

    /**
     * @param $argv
     * @return ArgvInput
     */
    protected function prepareInput($argv)
    {
        $pos = array_search('--', $argv);

        // cutting pass-through arguments
        if ($pos !== false) {
            $this->passThroughArgs = implode(' ', array_slice($argv, $pos+1));
            $argv = array_slice($argv, 0, $pos);
        }

        // loading from other directory
        $pos = array_search('--load-from', $argv);
        if ($pos !== false) {
            if (isset($argv[$pos +1])) {
                $this->dir = $argv[$pos +1];
                unset($argv[$pos +1]);
            }
            unset($argv[$pos]);
            // Make adjustments if '--load-from' points at a file.
            if (is_file($this->dir)) {
                $this->roboFile = basename($this->dir);
                $this->dir = dirname($this->dir);
            }
        }
        return new ArgvInput($argv);
    }

    public function shutdown()
    {
        $error = error_get_last();
        if (!is_array($error)) return;
        $this->writeln(sprintf("<error>ERROR: %s \nin %s:%d\n</error>", $error['message'], $error['file'], $error['line']));
    }

    /**
     * This is just a proxy error handler that checks the current error_reporting level.
     * In case error_reporting is disabled the error is marked as handled, otherwise
     * the normal internal error handling resumes.
     *
     * @return bool
     */
    public function handleError()
    {
        if (error_reporting() === 0) {
            return true;
        }
        return false;
    }
}

