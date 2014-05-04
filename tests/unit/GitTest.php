<?php

use AspectMock\Test as test;

class GitTest extends \Codeception\TestCase\Test
{
    use \Robo\Task\Git;
    /**
     * @var \AspectMock\Proxy\ClassProxy
     */
    protected $git;

    protected function _before()
    {
        $this->git = test::double('Robo\Task\GitStackTask', [
            'taskExec' => new \AspectMock\Proxy\Anything(),
            'getOutput' => new \Symfony\Component\Console\Output\NullOutput()
        ]);
    }
    // tests
    public function testGitStackRun()
    {
        $this->taskGitStack('git')->add('-A')->pull()->run();
        $this->git->verifyInvoked('taskExec', ['git add -A']);
        $this->git->verifyInvoked('taskExec', ['git pull  ']);
    }

    public function testGitStackCommands()
    {
        verify(
            $this->taskGitStack()
                ->cloneRepo('http://github.com/Codegyre/Robo')
                ->pull()
                ->add('-A')
                ->commit('changed')
                ->push()
                ->getCommand()
        )->equals("git clone http://github.com/Codegyre/Robo  && git pull   && git add -A && git commit -m 'changed'  && git push  ");
    }

}