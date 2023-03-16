<?php

namespace OsuWams\Commands;


use Consolidation\OutputFormatters\FormatterManager;
use Consolidation\OutputFormatters\Options\FormatterOptions;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Exception;

class CronCommand extends AcquiaCommand {

  public function __construct() {
    parent::__construct();
  }

  /**
   * @param $options
   *
   * @command cron:list
   * @return void
   * @throws \Consolidation\OutputFormatters\Exception\InvalidFormatException
   */
  public function listCrons($options = [
    'format' => 'table',
    'fields' => '',
  ]) {
    $this->say('Getting Applications...');
    $appHelper = new ChoiceQuestion('Select which Acquia Cloud Application you want to operate on', $this->getApplicationsId());
    $appName = $this->doAsk($appHelper);
    // Attempt to get the UUID of this application.
    try {
      $appUuId = $this->getUuidFromName($appName);
    }
    catch (Exception $e) {
      $this->say('Incorrect Application ID.');
    }
    // Get a list of environments for this App UUID.
    $this->writeln('Getting Environment ID\'s...');
    $envList = $this->getEnvironments($appUuId);
    // Get the Env for the scheduled jobs.
    $envHelper = new ChoiceQuestion('Which Environment do you want to see the domain list for...', $envList);
    $environment = $this->doAsk($envHelper);
    try {
      $envUuId = $this->getEnvUuIdFromApp($appUuId, $environment);
    }
    catch (Exception $e) {
      $this->say('Incorrect Environment and Application id.');
    }
    $cronList = $this->getCrons($envUuId);
    $opts = new FormatterOptions([], $options);
    $opts->setInput($this->input());
    $opts->setFieldLabels([
      'id' => 'Cron ID',
      'label' => 'Label',
      'command' => 'Command',
      'minute' => 'Minute',
      'hour' => 'Hour',
      "dayWeek" => 'Day of Week',
      'month' => 'Month',
      "dayMonth" => 'Day of Month',
    ]);
    $opts->setDefaultFields('label,command');
    $formatterManager = new FormatterManager();
    $formatterManager->write($this->output, $opts->getFormat(), new RowsOfFields($cronList), $opts);
  }

}
