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
   * @param $site
   * @param $fromENv
   * @param $toEnv
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
    $rsyncDown = $this->taskRsync()
      ->fromHost($fromUrl)
      ->fromPath("/mnt/gfs/${from[0]}/sites/${site}/")
      ->toPath("/tmp/${site}/")
      ->archive()
      ->compress()
      ->excludeVcs()
      ->progress();
    if ('y' === $this->ask("Do you want to copy ${site} files down? (y/n)")) {
      $rsyncDown->run();
    }
    $rsyncUp = $this->taskRsync()
      ->fromPath("/tmp/${site}/")
      ->toHost($toUrl)
      ->toPath("/mnt/gfs/${to[0]}/sites/${site}/")
      ->archive()
      ->compress()
      ->excludeVcs()
      ->progress();
    if ('y' === $this->ask("Do you want to push ${site} files up? (y/n)")) {
      $rsyncUp->run();
    }
    $this->say('Deleting temporary file copy.');
    $this->_deleteDir("/tmp/${site}");
  }
  /**
   * @command files:copy:down
   */
  public function copySiteFilesDown($appName, $site, $fromEnv) {
    $appUuId = $this->getUuidFromName($appName);
    $envUuIdFrom = $this->getEnvUuIdFromApp($appUuId, $fromEnv);
    $env = new Environments($this->client);
    $fromUrl = $env->get($envUuIdFrom)->sshUrl;
    $from = explode('@', $fromUrl);
    $rsyncDown = $this->taskRsync()
      ->fromHost($fromUrl)
      ->fromPath("/mnt/gfs/${from[0]}/sites/${site}/")
      ->toPath("/tmp/${site}/")
      ->archive()
      ->compress()
      ->excludeVcs()
      ->progress();
    if ('y' === $this->ask("Do you want to copy ${site} files down? (y/n)")) {
      $rsyncDown->run();
    }
  }

}
