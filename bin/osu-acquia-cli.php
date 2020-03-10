<?php

// If we're running from phar load the phar autoload file.
use OsuWams\Cli\AcquiaCli;
use Robo\Robo;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;

$pharPath = Phar::running(TRUE);
if ($pharPath) {
  $autoloaderPath = "$pharPath/vendor/autoload.php";
}
else {
  if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    $autoloaderPath = __DIR__ . '/vendor/autoload.php';
  }
  elseif (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    $autoloaderPath = __DIR__ . '/../vendor/autoload.php';
  }
  elseif (file_exists(__DIR__ . '/../../autoload.php')) {
    $autoloaderPath = __DIR__ . '/../../autoload.php';
  }
  else {
    die("Could not find autoloader. Run 'composer install'.");
  }
}
$classLoader = require $autoloaderPath;

// Customization variables
$input = new ArgvInput($argv);
$output = new ConsoleOutput();
$appVersion = trim(file_get_contents(__DIR__ . '/VERSION'));
$config = Robo::createConfiguration(['acquia-cli.yml']);
$app = new AcquiaCli($config, $input, $output);
$statusCode = $app->run($input, $output);
exit($statusCode);
