<?php

namespace Friendica\Core;

use Friendica\Core\Lock\ILock;

/**
 * Basic class for Locking with common functions (local acquired locks, releaseAll, ..)
 */
abstract class BaseLock implements ILock
{
	/**
	 * @var array The local acquired locks
	 */
	protected $acquiredLocks = [];

	/**
	 * Check if we've locally acquired a lock
	 *
	 * @param string key The Name of the lock
	 *
	 * @return bool      Returns true if the lock is set
	 */
	protected function hasAcquiredLock($key)
	{
		return isset($this->acquireLock[$key]) && $this->acquiredLocks[$key] === true;
	}

	/**
	 * Mark a locally acquired lock
	 *
	 * @param string $key The Name of the lock
	 */
	protected function markAcquire($key)
	{
		$this->acquiredLocks[$key] = true;
	}

	/**
	 * Mark a release of a locally acquired lock
	 *
	 * @param string $key The Name of the lock
	 */
	protected function markRelease($key)
	{
		unset($this->acquiredLocks[$key]);
	}

	/**
	 * {@inheritDoc}
	 */
	public function releaseAll($override = false)
	{
		$return = true;

		foreach ($this->acquiredLocks as $acquiredLock => $hasLock) {
			if (!$this->release($acquiredLock, $override)) {
				$return = false;
			}
		}

		return $return;
	}
}
