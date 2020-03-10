<?php


namespace OsuWams\Commands;


use AcquiaCloudApi\Endpoints\Environments;
use Exception;
use Symfony\Component\Console\Helper\Table;

class EnvironmentCommand extends AcquiaCommand {

  public function __construct() {
    parent::__construct();
    $this->environmentAdapter = new Environments($this->client);
  }

  /**
   * List All environments for the given Application.
   *
   * @param string $appName
   *  The Acquia CLoud Application Name.
   *
   * @command cloud:envs
   */
  public function listEnvironments($appName) {
    try {
      $appUuId = $this->getUuidFromName($appName);
    }
    catch (Exception $e) {
      $this->say('Incorrect Application Name.');
    }
    $output = $this->output();
    $table = new Table($output);
    $table->setHeaders(['UUID', 'Name', 'Label', 'Domains']);
    $environments = $this->environmentAdapter->getAll($appUuId);
    foreach ($environments as $environment) {
      $table->addRows([
        [
          $environment->uuid,
          $environment->name,
          $environment->label,
          implode("\n", $environment->domains),
        ],
      ]);
    }
    $table->render();
  }

}
