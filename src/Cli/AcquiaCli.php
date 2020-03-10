<?php

namespace OsuWams\Cli;

use Consolidation\AnnotatedCommand\CommandFileDiscovery;
use Robo\Application;
use Robo\Common\ConfigAwareTrait;
use Robo\Config\Config;
use Robo\Robo;
use Robo\Runner as RoboRunner;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


class AcquiaCli {

  const APPLICATION_NAME = 'OSU Acquia CLI';

  const REPOSITORY = 'osu-wams/acquia-cli';

  use ConfigAwareTrait;

  /**
   * @var \Robo\Runner $runner
   */
  private $runner;

  /**
   * AcquiaCli constructor.
   *
   * @param \Robo\Config\Config $config
   * @param \Symfony\Component\Console\Input\InputInterface|null $input
   * @param \Symfony\Component\Console\Output\OutputInterface|null $output
   *
   * @throws \Exception
   */
  public function __construct(
    Config $config,
    InputInterface $input = NULL,
    OutputInterface $output = NULL
  ) {
    if ($file = file_get_contents(dirname(dirname(__DIR__)) . '/VERSION')) {
      $version = trim($file);
    }
    else {
      throw new \Exception('No VERSION file');
    }
    $this->setConfig($config);
    $application = new Application(self::APPLICATION_NAME, $version);

    $container = Robo::createDefaultContainer($input, $output, $application, $config);
    $discovery = new CommandFileDiscovery();
    $discovery->setSearchPattern('*Command.php');
    $commandClasses = $discovery->discover(dirname(__DIR__) . '/Commands', '\OsuWams\Commands');
    // Instantiate Robo Runner.
    $this->runner = new RoboRunner();
    $this->runner->setContainer($container);
    $this->runner->registerCommandClasses($application, $commandClasses);
    $this->runner->setSelfUpdateRepository(self::REPOSITORY);
  }


  public function run(InputInterface $input, OutputInterface $output) {
    $statusCode = $this->runner->run($input, $output);

    return $statusCode;
  }


}
