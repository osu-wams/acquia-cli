<?php

namespace OsuWams;

use Robo\Tasks;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * This class handles the CLI setup for authenticating with Acquia Cloud.
 */
class CliSetup extends Tasks {

  /**
   * The input interface.
   *
   * @var \Symfony\Component\Console\Input\InputInterface
   */
  protected $input;

  /**
   * The Output interface.
   *
   * @var \Symfony\Component\Console\Output\OutputInterface
   */
  protected $output;

  /**
   * Constructor method for initializing input and output interfaces.
   *
   * @param InputInterface $input The input interface instance.
   * @param OutputInterface $output The output interface instance.
   *
   * @return void
   */
  public function __construct(InputInterface $input, OutputInterface $output) {
    $this->input = $input;
    $this->output = $output;
  }

  /**
   * Helper method for setting up CLI authentication with Acquia Cloud.
   *
   * Prompts the user to confirm whether they want to set up authentication.
   * If confirmed, it asks for the Acquia Cloud API Key and Secret, then attempts to save the credentials.
   * Provides feedback on the success or failure of saving the credentials.
   *
   * @return void
   */
  public function cliSetupHelper() {
    $startConfirm = $this->confirm("Not yet configured to authenticate with Acquia Cloud, do you want to setup?", "y");
    if ($startConfirm) {
      $apiKey = $this->askHidden("Please enter an Acquia Cloud API Key");
      $apiSecret = $this->askHidden("Please enter an Acquia Cloud Secret");

      if ($this->saveCredentials($apiKey, $apiSecret)) {
        $this->say("Credentials saved successfully.");
      }
      else {
        $this->say("Failed to save credentials.");
      }
    }
    else {
      $this->say("Setup cancelled. Exiting...");
    }
  }

  /**
   * Saves the provided API credentials to a configuration file.
   *
   * @param string $apiKey The API key.
   * @param string $apiSecret The API secret.
   *
   * @return bool TRUE on success, FALSE on failure.
   */
  private function saveCredentials(string $apiKey, string $apiSecret): bool {
    $configDir = $this->getConfigDir();
    if (!is_dir($configDir) && !mkdir($configDir, 0777, TRUE)) {
      return FALSE;
    }

    $configPath = "$configDir/acquia-cli.yml";
    $configContent = sprintf("acquia:\n  key: '%s'\n  secret: '%s'\n", $apiKey, $apiSecret);

    return file_put_contents($configPath, $configContent) !== FALSE;
  }

  /**
   * Retrieves the configuration directory path.
   *
   * @return string The path to the configuration directory.
   */
  private function getConfigDir(): string {
    return join(DIRECTORY_SEPARATOR, [
      getenv('HOME') ?: getenv('USERPROFILE'),
      '.acquia',
    ]);
  }

}
