<?php
namespace Everyman\Neo4j\Command;
use Everyman\Neo4j\Command,
	Everyman\Neo4j\Client;

/**
 * Get all relationship types known by the server
 */
class GetRelationshipTypes extends Command
{
	/**
	 * Create the factory function for this command
	 *
	 * Provides a hook for a Transport to map command class names to objects
	 *
	 * @return callable
	 */
	public static function factory()
	{
		return function (Client $client) {
			return new GetRelationshipTypes($client);
		};
	}

	/**
	 * Return the data to pass
	 *
	 * @return mixed
	 */
	protected function getData()
	{
		return null;
	}

	/**
	 * Return the transport method to call
	 *
	 * @return string
	 */
	protected function getMethod()
	{
		return 'get';
	}

	/**
	 * Return the path to use
	 *
	 * @return string
	 */
	protected function getPath()
	{
		return '/relationship/types';
	}

	/**
	 * Use the results
	 *
	 * @param integer $code
	 * @param array   $headers
	 * @param array   $data
	 * @return integer on failure
	 */
	protected function handleResult($code, $headers, $data)
	{
		if ((int)($code / 100) != 2) {
			$this->throwException('Unable to retrieve relationship types', $code, $headers, $data);
		}
		return $data;
	}
}

