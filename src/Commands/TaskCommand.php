<?php


namespace OsuWams\Commands;


use AcquiaCloudApi\Endpoints\Notifications;
use DateTime;
use DateTimeZone;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\VarDumper\VarDumper;

class TaskCommand extends AcquiaCommand {

  /**
   * @var \AcquiaCloudApi\Endpoints\Notifications
   */
  protected $notificationAdapter;

  public function __construct() {
    parent::__construct();
    $this->notificationAdapter = new Notifications($this->client);
  }

  /**
   * Get the task info for a given task id.
   *
   * @param string $taskId
   *  The acquia cloud task id.
   *
   * @command task:info
   */
  public function taskInfo($taskId) {
    $notificationDetails = $this->notificationAdapter->get($taskId);
    VarDumper::dump($notificationDetails);
  }

  /**
   * List all tasks for an application.
   *
   * @param string $appName
   *  The Acquia Cloud Application name.
   *
   * @command task:list
   * @throws \Exception
   */
  public function taskLists($appName) {
    $appUuId = $this->getUuidFromName($appName);
    $taskList = $this->notificationAdapter->getAll($appUuId);

    $output = $this->output();
    $table = new Table($output);
    $table->setHeaders([
      'UUID',
      'Label',
      'Status',
      'Completed',
    ]);
    foreach ($taskList as $task) {
      $completedAt = new DateTime($task->completed_at);
      $completedAt->setTimezone(new DateTimeZone('America/Los_Angeles'));
      $table->addRows([
        [
          $task->uuid,
          $task->label,
          $task->status,
          $completedAt->format('Y-m-d H:m:s a T'),
        ],
      ]);
    }
    $table->render();
  }

}
