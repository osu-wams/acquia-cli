<?php


namespace OsuWams\Commands;


use AcquiaCloudApi\Endpoints\Environments;
use Consolidation\OutputFormatters\FormatterManager;
use Consolidation\OutputFormatters\Options\FormatterOptions;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Exception;

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
  public function listEnvironments($appName, $options = [
    'format' => 'table',
    'fields' => '',
  ]) {
    try {
      $appUuId = $this->getUuidFromName($appName);
    }
    catch (Exception $e) {
      $this->say('Incorrect Application Name.');
    }

    $rows = [];
    $environments = $this->environmentAdapter->getAll($appUuId);
    /** @var \AcquiaCloudApi\Response\EnvironmentResponse $environment */
    foreach ($environments as $environment) {
      $rows[] = [
        'uuid' => $environment->uuid,
        'name' => $environment->name,
        'label' => $environment->label,
        'domains' => implode("\n", $environment->domains),
      ];
    }
    $opts = new FormatterOptions([], $options);
    $opts->setInput($this->input);
    $opts->setFieldLabels([
      'uuid' => 'Environment UUID',
      'name' => 'Environment Name',
      'label' => 'Label',
      'domains' => 'Domains',
    ]);
    $opts->setDefaultStringField('uuid');

    $formatterManager = new FormatterManager();
    $formatterManager->write($this->output, $opts->getFormat(), new RowsOfFields($rows), $opts);
  }

}
