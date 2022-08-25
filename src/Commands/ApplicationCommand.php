<?php


namespace OsuWams\Commands;


use AcquiaCloudApi\Endpoints\Applications;
use Consolidation\OutputFormatters\FormatterManager;
use Consolidation\OutputFormatters\Options\FormatterOptions;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;

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
  public function listApplications($options = [
    'format' => 'table',
    'fields' => '',
  ]) {
    // Generate a request object using the access token.
    $apps = $this->getAllApplications();
    $rows = [];

    /** @var \AcquiaCloudApi\Response\ApplicationResponse $app */
    foreach ($apps as $app) {
      $uuid = $app->uuid;
      $name = $app->name;
      $id = $app->hosting->id;
      $rows[] = ['uuid' => $uuid, 'name' => $name, 'id' => $id];
    }

    $opts = new FormatterOptions([], $options);
    $opts->setInput($this->input);
    $opts->setFieldLabels([
      'uuid' => 'UUID',
      'name' => 'Application Name',
      'id' => 'Application ID',
    ]);
    $opts->setDefaultStringField('uuid');

    $formatterManager = new FormatterManager();
    $formatterManager->write($this->output, $opts->getFormat(), new RowsOfFields($rows), $opts);
  }

}
