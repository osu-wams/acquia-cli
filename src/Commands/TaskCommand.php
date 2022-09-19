<?php


namespace OsuWams\Commands;


use AcquiaCloudApi\Endpoints\Notifications;
use Consolidation\OutputFormatters\FormatterManager;
use Consolidation\OutputFormatters\Options\FormatterOptions;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use DateTime;
use DateTimeZone;
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
  public function taskLists($appName, $options = [
    'format' => 'table',
    'fields' => '',
  ]) {
    $appUuId = $this->getUuidFromName($appName);
    $taskList = $this->notificationAdapter->getAll($appUuId);

    $rows = [];
    /** @var \AcquiaCloudApi\Response\NotificationResponse $task */
    foreach ($taskList as $task) {
      $completedAt = new DateTime($task->completed_at);
      $completedAt->setTimezone(new DateTimeZone('America/Los_Angeles'));
      $rows[] = [
        'uuid' => $task->uuid,
        'label' => $task->label,
        'status' => $task->status,
        'completed' => $completedAt->format('Y-m-d H:m:s a T'),
      ];
    }

    $opts = new FormatterOptions([], $options);
    $opts->setInput($this->input);
    $opts->setFieldLabels([
      'uuid' => 'Task UUID',
      'label' => 'Label',
      'status' => 'Status',
      'completed' => 'Completed',
    ]);
    $opts->setDefaultStringField('uuid');

    $formatterManager = new FormatterManager();
    $formatterManager->write($this->output, $opts->getFormat(), new RowsOfFields($rows), $opts);
  }

}
