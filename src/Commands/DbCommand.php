<?php


namespace OsuWams\Commands;


use AcquiaCloudApi\Endpoints\Databases;
use Exception;
use Symfony\Component\Console\Helper\Table;

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
   * @param string $appName
   *  The Acquia Cloud Application Name.
   * @param string $dbName
   *  The name of the new Database you want to create.
   *
   * @command db:create
   *
   * @throws \Exception
   */
  public function createDb($appName, $dbName) {
    $appUuId = $this->getUuidFromName($appName);
    $dbName = str_replace('.', '_', strtolower($dbName));
    $this->createDatabase($appUuId, $dbName);
  }

}
