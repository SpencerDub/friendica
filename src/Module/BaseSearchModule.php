<?php

namespace Friendica\Module;

use Friendica\BaseModule;
use Friendica\Content\ContactSelector;
use Friendica\Content\Pager;
use Friendica\Core\Renderer;
use Friendica\Core\Search;
use Friendica\DI;
use Friendica\Model;
use Friendica\Network\HTTPException;
use Friendica\Object\Search\ContactResult;
use Friendica\Object\Search\ResultList;
use Friendica\Util\Proxy as ProxyUtils;

/**
 * Base class for search modules
 */
class BaseSearchModule extends BaseModule
{
	/**
	 * Performs a contact search with an optional prefix
	 *
	 * @param string $search Search query
	 * @param string $prefix A optional prefix (e.g. @ or !) for searching
	 *
	 * @return string
	 * @throws HTTPException\InternalServerErrorException
	 * @throws \ImagickException
	 */
	public static function performContactSearch($search, $prefix = '')
	{
		$a      = DI::app();
		$config = DI::config();

		$type = Search::TYPE_ALL;

		$localSearch = $config->get('system', 'poco_local_search');

		$search = $prefix . $search;

		if (!$search) {
			return '';
		}

		$header = '';

		if (strpos($search, '@') === 0) {
			$search  = substr($search, 1);
			$type    = Search::TYPE_PEOPLE;
			$header  = DI::l10n()->t('People Search - %s', $search);

			if (strrpos($search, '@') > 0) {
				$results = Search::getContactsFromProbe($search);
			}
		}

		if (strpos($search, '!') === 0) {
			$search = substr($search, 1);
			$type   = Search::TYPE_FORUM;
			$header = DI::l10n()->t('Forum Search - %s', $search);
		}

		$args = DI::args();
		$pager = new Pager($args->getQueryString());

		if ($localSearch && empty($results)) {
			$pager->setItemsPerPage(80);
			$results = Search::getContactsFromLocalDirectory($search, $type, $pager->getStart(), $pager->getItemsPerPage());
		} elseif (strlen($config->get('system', 'directory')) && empty($results)) {
			$results = Search::getContactsFromGlobalDirectory($search, $type, $pager->getPage());
			$pager->setItemsPerPage($results->getItemsPage());
		}

		return self::printResult($results, $pager, $header);
	}

	/**
	 * Prints a human readable search result
	 *
	 * @param ResultList $results
	 * @param Pager      $pager
	 * @param string     $header
	 *
	 * @return string The result
	 * @throws HTTPException\InternalServerErrorException
	 * @throws \ImagickException
	 */
	protected static function printResult(ResultList $results, Pager $pager, $header = '')
	{
		if ($results->getTotal() == 0) {
			info(DI::l10n()->t('No matches'));
			return '';
		}

		$id      = 0;
		$entries = [];
		foreach ($results->getResults() as $result) {

			// in case the result is a contact result, add a contact-specific entry
			if ($result instanceof ContactResult) {

				$alt_text    = '';
				$location    = '';
				$about       = '';
				$accountType = '';
				$photo_menu  = [];

				// If We already know this contact then don't show the "connect" button
				if ($result->getCid() > 0 || $result->getPCid() > 0) {
					$connLink = "";
					$connTxt  = "";
					$contact  = Model\Contact::getById(
						($result->getCid() > 0) ? $result->getCid() : $result->getPCid()
					);

					if (!empty($contact)) {
						$photo_menu  = Model\Contact::photoMenu($contact);
						$details     = Contact::getContactTemplateVars($contact);
						$alt_text    = $details['alt_text'];
						$location    = $contact['location'];
						$about       = $contact['about'];
						$accountType = Model\Contact::getAccountType($contact);
					} else {
						$photo_menu = [];
					}
				} else {
					$connLink = DI::baseUrl()->get() . '/follow/?url=' . $result->getUrl();
					$connTxt  = DI::l10n()->t('Connect');

					$photo_menu['profile'] = [DI::l10n()->t("View Profile"), Model\Contact::magicLink($result->getUrl())];
					$photo_menu['follow']  = [DI::l10n()->t("Connect/Follow"), $connLink];
				}

				$photo = str_replace("http:///photo/", Search::getGlobalDirectory() . "/photo/", $result->getPhoto());

				$entry     = [
					'alt_text'     => $alt_text,
					'url'          => Model\Contact::magicLink($result->getUrl()),
					'itemurl'      => $result->getItem(),
					'name'         => $result->getName(),
					'thumb'        => ProxyUtils::proxifyUrl($photo, false, ProxyUtils::SIZE_THUMB),
					'img_hover'    => $result->getTags(),
					'conntxt'      => $connTxt,
					'connlnk'      => $connLink,
					'photo_menu'   => $photo_menu,
					'details'      => $location,
					'tags'         => $result->getTags(),
					'about'        => $about,
					'account_type' => $accountType,
					'network'      => ContactSelector::networkToName($result->getNetwork(), $result->getUrl()),
					'id'           => ++$id,
				];
				$entries[] = $entry;
			}
		}

		$tpl = Renderer::getMarkupTemplate('viewcontact_template.tpl');
		return Renderer::replaceMacros($tpl, [
			'title'     => $header,
			'$contacts' => $entries,
			'$paginate' => $pager->renderFull($results->getTotal()),
		]);
	}
}
