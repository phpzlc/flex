<?php
/**
 * PhpStorm.
 * User: Jay
 * Date: 2018/5/2
 */

namespace PHPZlc\Flex\Bundle\Command;


use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class InstallCommand extends Base
{
    protected function configure()
    {
        $this
            ->setName($this->command_pre . 'install')
            ->setDescription($this->description_pre . '食谱安装')
            ->addArgument('packageName', InputArgument::REQUIRED, 'phpzlc 包名')
        ;
    }

    /**
     * @var 包名
     */
    private $packageName;

    /**
     * @var 包的全名
     */
    private $packpagAllName;

    /**
     * @var 包的路径
     */
    private $packpagDirPath;

    /**
     * @var 包的食谱目录路径
     */
    private $packpageContribDirPath;

    /**
     * @var 包的食谱文件路径
     */
    private $packpageContribManifestPath;

    /**
     * @var array 包的食谱内容
     */
    private $packpageContribManifestContent;

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->packageName = $input->getArgument('packageName');

        $this->packpagAllName = 'phpzlc/' . $this->packageName;
        $this->packpagDirPath = $this->getRootPath() . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'phpzlc' . DIRECTORY_SEPARATOR . $this->packageName;
        $this->packpageContribDirPath = $this->packpagDirPath .  DIRECTORY_SEPARATOR . 'Contrib';
        $this->packpageContribManifestPath = $this->packpageContribDirPath . DIRECTORY_SEPARATOR . 'manifest.json';

        $this->io->title('执行 ' . $this->packpagAllName . ' 安装程序');

        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion('是否确认执行,执行会覆盖业务代码，建议先备份现有代码在执行?(y/n): ', false);
        if (!$helper->ask($input, $output, $question)) {
            $this->io->warning('执行中断');
            return Command::SUCCESS;
        }

        $filesystem = new Filesystem();

        if ($filesystem->exists($this->packpageContribManifestPath)) {
            $packpageContribManifestcontent = file_get_contents($this->packpageContribManifestPath);
            $packpageContribManifestcontent = str_replace(array("\n", "\r"), '', $packpageContribManifestcontent);
            $this->packpageContribManifestContent = json_decode(trim($packpageContribManifestcontent), true);
            if(empty($this->packpageContribManifestContent) || !is_array($this->packpageContribManifestContent)){
                $this->io->error('安装失败：食谱格式错误');

                return Command::FAILURE;
            }

            if(array_key_exists('copy-from-package', $this->packpageContribManifestContent)){
                $this->io->title('run copy-from-package:');
                foreach ($this->packpageContribManifestContent['copy-from-package'] as $originFile => $targetFile){
                    $originFile = str_replace('/', DIRECTORY_SEPARATOR, $this->packpagDirPath . DIRECTORY_SEPARATOR. $originFile);
                    $targetFile = str_replace('/', DIRECTORY_SEPARATOR, $this->getRootPath() . DIRECTORY_SEPARATOR . $targetFile);

                    if(is_file($originFile)) {
                        if ($filesystem->exists($targetFile)) {
                            if (strpos($targetFile, 'vendor') !== false) {
                                $filesystem->rename($targetFile, $targetFile . '.' . time() . '.back');
                            } else {
                                $filesystem->remove($targetFile);
                            }
                        }

                        $filesystem->copy($originFile, $targetFile);
                    }else{
                        $filesystem->mirror($originFile, $targetFile);
                    }

                    $this->io->text($originFile . '=>' . $targetFile );
                }
                $this->io->title('copy-from-package run 成功');
            }

            if($filesystem->exists($this->packpageContribDirPath . DIRECTORY_SEPARATOR . 'README.md')){
                $this->io->title('run README:');
                $readmeContent = file_get_contents($this->packpageContribDirPath . DIRECTORY_SEPARATOR . 'README.md');
                $readmeContent = <<<EOF

## {$this->packpagAllName}

{$readmeContent}

EOF;
                $filesystem->appendToFile($this->getRootPath() . DIRECTORY_SEPARATOR . 'README.md', $readmeContent);

                $this->io->warning('请打开项目的README.md 查看变化, 必要时按实际需要变更');
                $this->io->title('README run 成功');
            }

        }else{
            $this->io->error('安装失败：未找到食谱');

            return Command::FAILURE;
        }

        $this->io->success('安装成功');

        return Command::SUCCESS;
    }
}

