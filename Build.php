<?php
require 'vendor/autoload.php';

use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\SingleCommandApplication;

if (!class_exists('WPWhatsAppChatBuilder')) {
  class WPWhatsAppChatBuilder
  {
    protected static $instance = null;

    public function __construct()
    {
      $this->run_build();
    }

    public function run_build()
    {
      (new SingleCommandApplication())
        ->setName('Build Generator')
        ->setCode(function (InputInterface $input, OutputInterface $output) {
          $fs = new Filesystem();
          $config = json_decode(file_get_contents('build-config.json'), true);

          $plugin_build_dir = "build";
          $plugin_name = "wp-whatsapp-chat";
          $plugin_file_name = "$plugin_name.zip";

          if ($fs->exists($plugin_build_dir)) {
            $fs->remove($plugin_build_dir);
          }
          $fs->mkdir($plugin_build_dir);

          $tempDir = sys_get_temp_dir() . '/plugin_temp_' . uniqid();
          $fs->mkdir($tempDir);

          foreach ($config['include'] as $item) {
            $source = $item;
            $destination = "$tempDir/$item";
            if ($fs->exists($source)) {
              if (is_dir($source)) {
                $fs->mirror($source, $destination);
              } else {
                $fs->copy($source, $destination);
              }
              $output->writeln("<info>Copied: $item</info>");
            } else {
              $output->writeln("<error>Warning: $item not exists.</error>");
            }
          }

          foreach ($config['exclude'] as $item) {
            $target = "$tempDir/$item";
            if ($fs->exists($target)) {
              $fs->remove($target);
              $output->writeln("<comment>Removed: $item</comment>");
            }
          }

          $zipFile = "$plugin_build_dir/$plugin_file_name";
          $zip = new ZipArchive();
          if ($zip->open($zipFile, ZipArchive::CREATE) === true) {
            $finder = new Finder();
            $finder->files()->in($tempDir);
            foreach ($finder as $file) {
              $relativePath = $file->getRelativePathname();
              $zip->addFile($file->getRealPath(), $relativePath);
            }
            $zip->close();
            $output->writeln("<info>ZIP created: $zipFile</info>");
          }

          $fs->remove($tempDir);

          $output->writeln("<info>✅ Build successfully! The ZIP file is in /$plugin_build_dir</info>");
          return Command::SUCCESS;
        })
        ->run();
    }

    public static function get_instance()
    {
      if (self::$instance === null) {
        self::$instance = new self();
      }
      return self::$instance;
    }
  }

  WPWhatsAppChatBuilder::get_instance();
}