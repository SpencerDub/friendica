<?php

namespace Friendica\Factory\Mastodon;

use Friendica\App\BaseURL;
use Friendica\Model\APContact;
use Friendica\Model\Contact;
use Friendica\Model\Introduction;
use Friendica\Network\HTTPException;
use Friendica\BaseFactory;
use Psr\Log\LoggerInterface;

class FollowRequest extends BaseFactory
{
	/** @var BaseURL */
	protected $baseUrl;

	public function __construct(LoggerInterface $logger, BaseURL $baseURL)
	{
		parent::__construct($logger);

		$this->baseUrl = $baseURL;
	}

	/**
	 * @param Introduction $introduction
	 * @return \Friendica\Api\Entity\Mastodon\FollowRequest
	 * @throws HTTPException\InternalServerErrorException
	 * @throws \ImagickException
	 */
	public function createFromIntroduction(Introduction $introduction)
	{
		$cdata = Contact::getPublicAndUserContacID($introduction->{'contact-id'}, $introduction->uid);

		if (empty($cdata)) {
			$this->logger->warning('Wrong introduction data', ['Introduction' => $introduction]);
			throw new HTTPException\InternalServerErrorException('Wrong introduction data');
		}

		$publicContact = Contact::getById($cdata['public']);
		$userContact = Contact::getById($cdata['user']);

		$apcontact = APContact::getByURL($publicContact['url'], false);

		return new \Friendica\Api\Entity\Mastodon\FollowRequest($this->baseUrl, $introduction->id, $publicContact, $apcontact, $userContact);
	}
}
