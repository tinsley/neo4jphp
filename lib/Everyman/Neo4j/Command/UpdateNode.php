<?php
namespace Everyman\Neo4j\Command;
use Everyman\Neo4j\Command,
	Everyman\Neo4j\Client,
	Everyman\Neo4j\Exception,
	Everyman\Neo4j\Node;

/**
 * Update a node
 */
class UpdateNode extends Command
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
			return new UpdateNode($client, $node);
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
		return $this->node->getProperties();
	}

	/**
	 * Return the transport method to call
	 *
	 * @return string
	 */
	protected function getMethod()
	{
		return 'put';
	}

	/**
	 * Return the path to use
	 *
	 * @return string
	 */
	protected function getPath()
	{
		if (!$this->node->hasId()) {
			throw new Exception('No node id specified');
		}
		return '/node/'.$this->node->getId().'/properties';
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
			$this->getEntityCache()->setCachedEntity($this->node);
			return true;
		} else {
			$this->throwException('Unable to update node', $code, $headers, $data);
		}
	}
}

