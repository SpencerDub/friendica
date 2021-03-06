<?php
/**
 * @file mod/regmod.php
 */

use Friendica\App;
use Friendica\Core\Worker;
use Friendica\Database\DBA;
use Friendica\DI;
use Friendica\Model\Register;
use Friendica\Model\User;
use Friendica\Module\Security\Login;

function user_allow($hash)
{
	$register = Register::getByHash($hash);
	if (!DBA::isResult($register)) {
		return false;
	}

	$user = User::getById($register['uid']);
	if (!DBA::isResult($user)) {
		exit();
	}

	Register::deleteByHash($hash);

	DBA::update('user', ['blocked' => false, 'verified' => true], ['uid' => $register['uid']]);

	$profile = DBA::selectFirst('profile', ['net-publish'], ['uid' => $register['uid'], 'is-default' => true]);

	if (DBA::isResult($profile) && $profile['net-publish'] && DI::config()->get('system', 'directory')) {
		$url = DI::baseUrl() . '/profile/' . $user['nickname'];
		Worker::add(PRIORITY_LOW, "Directory", $url);
	}

	$l10n = DI::l10n()->withLang($register['language']);

	$res = User::sendRegisterOpenEmail(
		$l10n,
		$user,
		DI::config()->get('config', 'sitename'),
		DI::baseUrl()->get(),
		($register['password'] ?? '') ?: 'Sent in a previous email'
	);

	if ($res) {
		info(DI::l10n()->t('Account approved.') . EOL);
		return true;
	}
}

// This does not have to go through user_remove() and save the nickname
// permanently against re-registration, as the person was not yet
// allowed to have friends on this system
function user_deny($hash)
{
	$register = Register::getByHash($hash);
	if (!DBA::isResult($register)) {
		return false;
	}

	$user = User::getById($register['uid']);
	if (!DBA::isResult($user)) {
		exit();
	}

	DBA::delete('user', ['uid' => $register['uid']]);

	Register::deleteByHash($register['hash']);

	notice(DI::l10n()->t('Registration revoked for %s', $user['username']) . EOL);
	return true;
}

function regmod_content(App $a)
{
	if (!local_user()) {
		info(DI::l10n()->t('Please login.') . EOL);
		return Login::form(DI::args()->getQueryString(), intval(DI::config()->get('config', 'register_policy')) === \Friendica\Module\Register::CLOSED ? 0 : 1);
	}

	if (!is_site_admin() || !empty($_SESSION['submanage'])) {
		notice(DI::l10n()->t('Permission denied.') . EOL);
		return '';
	}

	if ($a->argc != 3) {
		exit();
	}

	$cmd = $a->argv[1];
	$hash = $a->argv[2];

	if ($cmd === 'deny') {
		user_deny($hash);
		DI::baseUrl()->redirect('admin/users/');
	}

	if ($cmd === 'allow') {
		user_allow($hash);
		DI::baseUrl()->redirect('admin/users/');
	}
}
