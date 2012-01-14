<?php
namespace Everyman\Neo4j\Command;
use Everyman\Neo4j\Command,
	Everyman\Neo4j\Client,
	Everyman\Neo4j\Exception,
	Everyman\Neo4j\Node;

/**
 * Delete a node
 */
class DeleteNode extends Command
{
	protected $node = null;

	/**
	 * Create the factory function for this command
	 *
	 * Provides a hook for a Transport to map command class names to objects
	 *
	 * @return callable
	 */
	public static function factory()
	{
		return function (Client $client, Node $node) {
			return new DeleteNode($client, $node);
		};
	}

	/**
	 * Set the node to drive the command
	 *
	 * @param Client $client
	 * @param Node $node
	 */
	public function __construct(Client $client, Node $node)
	{
		parent::__construct($client);
		$this->node = $node;
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
		return 'delete';
	}

	/**
	 * Return the path to use
	 *
	 * @return string
	 */
	protected function getPath()
	{
		if (!$this->node->hasId()) {
			throw new Exception('No node id specified for delete');
		}
		return '/node/'.$this->node->getId();
	}

	/**
	 * Use the results
	 *
	 * @param integer $code
	 * @param array   $headers
	 * @param array   $data
	 * @return boolean true on success
	 * @throws Exception on failure
	 */
	protected function handleResult($code, $headers, $data)
	{
		if ((int)($code / 100) == 2) {
			$this->getEntityCache()->deleteCachedEntity($this->node);
			return true;
		} else {
			$this->throwException('Unable to delete node', $code, $headers, $data);
		}
	}
}

