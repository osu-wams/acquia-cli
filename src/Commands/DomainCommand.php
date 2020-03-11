<?php


namespace OsuWams\Commands;


use Symfony\Component\Console\Question\ChoiceQuestion;

class DomainCommand extends AcquiaCommand {

  public function __construct() {
    parent::__construct();
  }

  /**
   * Flush a set of domains from Acquia Cloud,
   *
   * @throws \Exception
   */
  public function flushSiteVarnish() {
    $appHelper = new ChoiceQuestion('Select which Acquia Cloud Application you want to deploy a site on', $this->getApplicationsId());
    $appName = $this->doAsk($appHelper);
    $appUuid = $this->getUuidFromName($appName);
    $envList = $this->getEnvironments($appUuid);
    $envHelper = new ChoiceQuestion('Select which Environment to perform the flush on.', $envList);
    $env = $this->doAsk($envHelper);
    $envUuid = $this->getEnvUuIdFromApp($appUuid, $env);
    $domains = $this->getDomains($envUuid);
    $domainHelper = new ChoiceQuestion("Which Domains do you want to flush? Separate multiple by comma", $domains);
    $domainHelper->setMultiselect(TRUE);
    $domain = $this->doAsk($domainHelper);
    $this->output()
      ->writeln('Flushing Domains ' . implode(',', $domain));
    $this->flushVarnish($envUuid, $domain);
  }

}
