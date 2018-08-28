<?php

/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) LOCKON CO.,LTD. All Rights Reserved.
 *
 * http://www.lockon.co.jp/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Eccube\Command;

use Eccube\Entity\Plugin;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class PluginInstallCommand extends Command
{
    protected static $defaultName = 'eccube:plugin:install';

    use PluginCommandTrait;

    protected function configure()
    {
        $this
            ->addOption('path', null, InputOption::VALUE_OPTIONAL, 'path of tar or zip')
            ->addOption('code', null, InputOption::VALUE_OPTIONAL, 'plugin code')
            ->setDescription('Install plugin from local.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $path = $input->getOption('path');
        $code = $input->getOption('code');

        // アーカイブからインストール
        if ($path) {
            if ($this->pluginService->install($path)) {
                $io->success('Installed.');

                return;
            }
        }

        // 設置済ファイルからインストール
        if ($code) {

            $pluginDir = $this->pluginService->calcPluginDir($code);
            $this->pluginService->checkPluginArchiveContent($pluginDir);
            $config = $this->pluginService->readConfig($pluginDir);

            // 依存プラグインが有効になっていない場合はエラー
            $requires = $this->pluginService->getPluginRequired($config);
            $notInstalledOrDisabled = array_filter($requires, function($req) {
                $code = preg_replace('/^ec-cube\//', '', $req['name']);
                /** @var Plugin $DependPlugin */
                $DependPlugin = $this->pluginRepository->findOneBy(['code' => $code]);
                return $DependPlugin ? $DependPlugin->isEnabled() == false : true;
            });

            if (!empty($notInstalledOrDisabled)) {
                $names = array_map(function($p) { return $p['name']; }, $notInstalledOrDisabled);
                $io->error(implode(', ', $names)."を有効化してください。");
                return 1;
            }

            $this->pluginService->checkSamePlugin($config['code']);
            $this->pluginService->postInstall($config, $config['source']);

            $this->clearCache($io);
            $io->success('Installed.');

            return;
        }

        $io->error('path or code is required.');
    }
}
