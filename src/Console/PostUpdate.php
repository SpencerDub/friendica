<?php

namespace Friendica\Console;

use Friendica\App;
use Friendica\Core\Config\Configuration;
use Friendica\Core\L10n\L10n;
use Friendica\Core\Update;

/**
 * Performs database post updates
 *
 * License: AGPLv3 or later, same as Friendica
 *
 * @author Tobias Diekershoff <tobias.diekershoff@gmx.net>
 * @author Hypolite Petovan <hypolite@mrpetovan.com>
 */
class PostUpdate extends \Asika\SimpleConsole\Console
{
	protected $helpOptions = ['h', 'help', '?'];

	/**
	 * @var App\Mode
	 */
	private $appMode;
	/**
	 * @var Configuration
	 */
	private $config;
	/**
	 * @var L10n
	 */
	private $l10n;

	protected function getHelp()
	{
		$help = <<<HELP
console postupdate - Performs database post updates
Usage
        bin/console postupdate [-h|--help|-?] [--reset <version>]

Options
    -h|--help|-?      Show help information
    --reset <version> Reset the post update version
HELP;
		return $help;
	}

	public function __construct(App\Mode $appMode, Configuration $config, L10n $l10n, array $argv = null)
	{
		parent::__construct($argv);

		$this->appMode = $appMode;
		$this->config = $config;
		$this->l10n = $l10n;
	}

	protected function doExecute()
	{
		$a = \Friendica\DI::app();

		if ($this->getOption($this->helpOptions)) {
			$this->out($this->getHelp());
			return 0;
		}

		$reset_version = $this->getOption('reset');
		if (is_bool($reset_version)) {
			$this->out($this->getHelp());
			return 0;
		} elseif ($reset_version) {
			$this->config->set('system', 'post_update_version', $reset_version);
			echo $this->l10n->t('Post update version number has been set to %s.', $reset_version) . "\n";
			return 0;
		}

		if ($this->appMode->isInstall()) {
			throw new \RuntimeException('Database isn\'t ready or populated yet');
		}

		echo $this->l10n->t('Check for pending update actions.') . "\n";
		Update::run($a->getBasePath(), true, false, true, false);
		echo $this->l10n->t('Done.') . "\n";

		echo $this->l10n->t('Execute pending post updates.') . "\n";

		while (!\Friendica\Database\PostUpdate::update()) {
			echo '.';
		}

		echo "\n" . $this->l10n->t('All pending post updates are done.') . "\n";

		return 0;
	}
}
