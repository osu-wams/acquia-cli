<?php


namespace OsuWams\Commands;


use AcquiaCloudApi\Endpoints\Environments;

/**
 * Class CopyFilesCommand
 *
 * @package OsuWams\Commands
 */
class CopyFilesCommand extends AcquiaCommand {

  public function __construct() {
    parent::__construct();
  }

  /**
   * Copy a sites files from one environment to the other.
   *
   * @param $appName
   *   The Acquia CLoud Application Name.
   * @param $site
   *   The FQDN of the site to work on.
   * @param $fromENv
   *   The Environment to copy from.
   * @param $toEnv
   *   The Environment to copy to.
   *
   * @command files:copy
   *
   * @throws \Exception
   */
  public function copySiteFiles($appName, $site, $fromENv, $toEnv) {
    $appUuId = $this->getUuidFromName($appName);
    $envUuIdFrom = $this->getEnvUuIdFromApp($appUuId, $fromENv);
    $envUuIdTo = $this->getEnvUuIdFromApp($appUuId, $toEnv);
    $env = new Environments($this->client);
    $fromUrl = $env->get($envUuIdFrom)->sshUrl;
    $toUrl = $env->get($envUuIdTo)->sshUrl;
    $from = explode('@', $fromUrl);
    $to = explode('@', $toUrl);
    $platform = $env->get($envUuIdFrom)->platform;
    if ($platform === 'cloud-next') {
      $remotePathFrom = "/shared/sites/$site/";
      $remotePathTo = "/shared/sites/$site/";
    }
    else {
      $remotePathFrom = "/mnt/gfs/${from[0]}/sites/$site/";
      $remotePathTo = "/mnt/gfs/${to[0]}/sites/$site/";
    }
    $rsyncDown = $this->taskRsync()
      ->fromHost($fromUrl)
      ->fromPath($remotePathFrom)
      ->toPath("/tmp/$site/")
      ->archive()
      ->compress()
      ->excludeVcs()
      ->progress();
    if ('y' === $this->ask("Do you want to copy $site files down? (y/n)")) {
      $rsyncDown->run();
    }
    $rsyncUp = $this->taskRsync()
      ->fromPath("/tmp/$site/")
      ->toHost($toUrl)
      ->toPath($remotePathTo)
      ->archive()
      ->compress()
      ->excludeVcs()
      ->progress();
    if ('y' === $this->ask("Do you want to push $site files up? (y/n)")) {
      $rsyncUp->run();
    }
    $this->say('Deleting temporary file copy.');
    $this->_deleteDir("/tmp/$site");
  }

  /**
   * Copy a sites files down from Acquia Cloud.
   *
   * @command files:copy:down
   *
   * @param string $appName
   *   The Acquia CLoud Application Name.
   * @param $site
   *   The FQDN of the site to work on.
   * @param $fromEnv
   *   The Environment to copy from
   * @param null|string $destination
   *   Optional: Leave bank to copy into current calling directory or pass a
   * full system path to copy the files too.
   *
   */
  public function copySiteFilesDown($appName, $site, $fromEnv, $destination = NULL) {
    if (is_null($destination)) {
      $destination = getcwd();
    }
    $appUuId = $this->getUuidFromName($appName);
    $envUuIdFrom = $this->getEnvUuIdFromApp($appUuId, $fromEnv);
    $env = new Environments($this->client);
    $fromUrl = $env->get($envUuIdFrom)->sshUrl;
    $from = explode('@', $fromUrl);
    $platform = $env->get($envUuIdFrom)->platform;
    if ($platform === 'cloud-next') {
      $remotePath = "/shared/sites/$site/";
    }
    else {
      $remotePath = "/mnt/gfs/${from[0]}/sites/$site/";
    }
    $rsyncDown = $this->taskRsync()
      ->fromHost($fromUrl)
      ->fromPath($remotePath)
      ->toPath("$destination/$site/")
      ->archive()
      ->compress()
      ->excludeVcs()
      ->progress();
    if ('y' === $this->ask("Do you want to copy $site files down? (y/n)")) {
      $rsyncDown->run();
    }
  }

}
