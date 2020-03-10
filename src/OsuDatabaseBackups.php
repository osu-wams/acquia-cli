<?php


namespace OsuWams;


use AcquiaCloudApi\Endpoints\DatabaseBackups;
use AcquiaCloudApi\Response\OperationResponse;

/**
 * Class OsuDatabaseBackups
 *
 * @package OsuWams
 */
class OsuDatabaseBackups extends DatabaseBackups {

  /**
   * Perform a delete against Acquia Cloud for the Database.
   *
   * @param $environmentUuid
   * @param $dbName
   * @param $backupId
   *
   * @return \AcquiaCloudApi\Response\OperationResponse
   */
  public function delete($environmentUuid, $dbName, $backupId) {
    return new OperationResponse(
      $this->client->request(
        'delete',
        "/environments/${environmentUuid}/databases/${dbName}/backups/${backupId}"
      )
    );
  }

}
