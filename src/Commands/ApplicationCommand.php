<?php


namespace OsuWams\Commands;


use AcquiaCloudApi\Endpoints\Applications;
use Symfony\Component\Console\Helper\Table;

/**
 * Class ApplicationCommand
 *
 * @package OsuWams\Commands
 */
class ApplicationCommand extends AcquiaCommand {

  /**
   * @var \AcquiaCloudApi\Endpoints\Applications
   */
  protected $applicationAdapter;

  public function __construct() {
    parent::__construct();
    $this->applicationAdapter = new Applications($this->client);
  }

  /**
   * List all the cloud applications you have access to.
   *
   * @command cloud:apps
   */
  public function listApplications() {
    // Generate a request object using the access token.
    $apps = $this->applicationAdapter->getAll();

    $output = $this->output();
    $table = new Table($output);
    $table->setHeaders(['UUID', 'Name', 'ID']);
    foreach ($apps as $app) {
      $uuid = $app->uuid;
      $name = $app->name;
      $id = $app->hosting->id;
      $table->addRows([
        [
          $uuid,
          $name,
          $id,
        ],
      ]);
    }
    $table->render();
  }

}
