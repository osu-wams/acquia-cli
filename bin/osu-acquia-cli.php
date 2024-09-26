<?php

// If we're running from phar load the phar autoload file.
use OsuWams\Cli\AcquiaCli;
use OsuWams\CliSetup;
use Robo\Robo;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Filesystem\Path;

const EX_CONFIG = 78;

$pharPath = Phar::running(TRUE);
if ($pharPath) {
  $root = $pharPath;
  $autoloaderPath = "$pharPath/vendor/autoload.php";
}
else {
  if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    $root = dirname(__DIR__);
    $autoloaderPath = __DIR__ . '/vendor/autoload.php';
  }
  elseif (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    $root = dirname(__DIR__) . '/../..';
    $autoloaderPath = __DIR__ . '/../vendor/autoload.php';
  }
  elseif (file_exists(__DIR__ . '/../../autoload.php')) {
    $root = dirname(__DIR__) . '/../../..';
    $autoloaderPath = __DIR__ . '/../../autoload.php';
  }
  else {
    die("Could not find autoloader. Run 'composer install'.");
  }
}
$classLoader = require $autoloaderPath;
$environment = [];
if (getenv('ACQUIACLI_KEY')) {
  $environment['acquia']['key'] = getenv('ACQUIACLI_KEY');
}
if (getenv('ACQUIACLI_SECRET')) {
  $environment['acquia']['secret'] = getenv('ACQUIACLI_SECRET');
}
// Get the file path for the local project config.
$localConfig = join(DIRECTORY_SEPARATOR, [
  Path::getDirectory(__DIR__),
  'acquia-cli.yml',
]);
// Get the file path for the global config.
$globalConfig = join(DIRECTORY_SEPARATOR, [
  Path::getHomeDirectory(),
  '.acquia/acquia-cli.yml',
]);
// Customization variables
$input = new ArgvInput($argv);
$output = new ConsoleOutput();
$appVersion = trim(file_get_contents(dirname(__DIR__) . '/VERSION'));
$config = Robo::createConfiguration([$globalConfig, $localConfig]);
// Check to see if we have env variables and update our config object.
// Environment variables should override any other variables loaded from config
// files
if (isset($environment['acquia']['key']) && isset($environment['acquia']['secret'])) {
  $config->set('acquia.key', $environment['acquia']['key']);
  $config->set('acquia.secret', $environment['acquia']['secret']);
}
if (is_null($config->get('acquia.key')) || is_null($config->get('acquia.secret'))) {
  $setupHelper = new CliSetup($input, $output);
  $statusCode = $setupHelper->cliSetupHelper();
  exit($statusCode);
}
$app = new AcquiaCli($config, $input, $output);
$statusCode = $app->run($input, $output);
exit($statusCode);
