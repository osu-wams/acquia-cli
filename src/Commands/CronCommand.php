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
   * Get a list of Scheduled Jobs.
   *
   * Optional arguments: app,env. If app and/or env are not provided a helper will ask you to select
   * from a generated list.
   *
   * @command cron:list
   *
   * @param array $options
   * @option $app The Acquia Cloud Application name: prod:shortname
   * @option $env The Environment short name: dev|prod|test
   * @option $format Format the result data. Available formats:
   *   csv,json,list,null,php,print-r,sections,string,table,tsv,var_dump,var_export,xml,yaml
   * @option $fields Available fields: Cron ID (id), Label, (label), Command
   *   (command), Minute (minute), Hour (hour), Day of Week (dayWeek), Month
   *   (month), Day of Month (dayMonth)
   *
   * @usage cron:list
   * @usage cron:list --app=prod:app
   * @usage cron:list --env=prod
   * @usage cron:list --app=prod:app --env=prod --format=json
   *
   * @return void
   * @throws \Consolidation\OutputFormatters\Exception\InvalidFormatException
   */
  public function listCrons(array $options = [
    'app' => NULL,
    'env' => NULL,
    'format' => 'table',
    'fields' => 'label,command',
  ]) {
    if (is_null($options['app'])) {
      $this->say('Getting Applications...');
      $appHelper = new ChoiceQuestion('Select which Acquia Cloud Application you want to operate on', $this->getApplicationsId());
      $appName = $this->doAsk($appHelper);
    }
    else {
      $appName = $options['app'];
    }
    // Attempt to get the UUID of this application.
    try {
      $appUuId = $this->getUuidFromName($appName);
    }
    catch (Exception $e) {
      $this->say('Incorrect Application ID.');
    }
    if (is_null($options['env'])) {
      // Get a list of environments for this App UUID.
      $this->writeln('Getting Environment ID\'s...');
      $envList = $this->getEnvironments($appUuId);
      // Get the Env for the scheduled jobs.
      $envHelper = new ChoiceQuestion('Which Environment do you want to see the domain list for...', $envList);
      $environment = $this->doAsk($envHelper);
    }
    else {
      $environment = $options['env'];
    }
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
