<?php


namespace OsuWams\Commands;


use AcquiaCloudApi\Endpoints\Databases;
use Exception;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;

class DbCommand extends AcquiaCommand {

  /**
   * @var \AcquiaCloudApi\Endpoints\Databases
   */
  protected $dbAdapter;

  public function __construct() {
    parent::__construct();
    $this->dbAdapter = new Databases($this->client);
  }

  /**
   * List All Databases for the given Application.
   *
   * @param string $appName
   *  The Acquia CLoud Application Name.
   *
   * @command cloud:dbs
   */
  public function listDatabases($appName) {
    try {
      $appUuId = $this->getUuidFromName($appName);
    }
    catch (Exception $e) {
      $this->say('Incorrect Application Name.');
    }
    $envDbs = $this->dbAdapter->getAll($appUuId);
    $output = $this->output();
    $table = new Table($output);
    $table->setHeaders(['Name']);
    foreach ($envDbs as $db) {
      $table->addRow([$db->name]);
    }
    $table->render();
  }

  /**
   * Deploy Database to a given environment.
   *
   * @param $appName
   * @param $dbName
   * @param $fromEnv
   * @param $toEnv
   *
   * @command db:deployto
   *
   * @throws \Exception
   */
  public function deployDbTo($appName, $dbName, $fromEnv, $toEnv) {
    $appUuId = $this->getUuidFromName($appName);
    $envUuIdFrom = $this->getEnvUuIdFromApp($appUuId, $fromEnv);
    $envUuIdTo = $this->getEnvUuIdFromApp($appUuId, $toEnv);
    $this->copyDb($dbName, $envUuIdFrom, $envUuIdTo);
  }

  /**
   * Create a new Database.
   *
   * @command db:create
   *
   * @throws \Exception
   */
  public function createDb() {
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
    $dbNameHelper = new Question('Production domain name: ');
    $dbName = $this->doAsk($dbNameHelper);
    if (!is_null($dbName)) {
      $this->say('Creating database > ' . $dbName);
      $dbName = str_replace('.', '_', strtolower($dbName));
      $makeItSo = $this->confirm("Do want to create database: ${dbName}?");
      if ($makeItSo) {
        $this->createDatabase($appUuId, $dbName);
        return;
      }
    }
    else {
      $this->say('Nothing provided, stopping');
      exit();
    }
  }

  /**
   * Delete a database.
   *
   * @command db:delete
   */
  public function deleteDb() {
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
    $this->say('Getting Databases...');
    $dbHelper = new ChoiceQuestion('Select which Database to delete', $this->getDatabases($appUuId));
    $dbName = $this->doAsk($dbHelper);
    if (!is_null($dbName)) {
      $makeItSo = $this->confirm("Do you want to delete this database: {$dbName}?");
      if ($makeItSo) {
        $this->deleteDatabase($appUuId, $dbName);
      }
    }
  }

}
