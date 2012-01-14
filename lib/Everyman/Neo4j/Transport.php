<?php
namespace Everyman\Neo4j;

/**
 * Class for communicating with an HTTP JSON endpoint
 */
class Transport
{
	const GET    = 'GET';
	const POST   = 'POST';
	const PUT    = 'PUT';
	const DELETE = 'DELETE';

	protected $scheme = 'http';
	protected $host = 'localhost';
	protected $port = 7474;
	protected $path = '/db/data';
	protected $username = null;
	protected $password = null;

	protected $handle = null;

	protected $commandMap = array();

	/**
	 * Set the host and port of the endpoint
	 *
	 * @param string $host
	 * @param integer $port
	 */
	public function __construct($host='localhost', $port=7474)
	{
		$this->host = $host;
		$this->port = $port;

		$this->commandMap = array(
			'addToIndex' => 'Everyman\Neo4j\Command\AddToIndex',
			'commitBatch' => 'Everyman\Neo4j\Command\Batch\Commit',
			'createNode' => 'Everyman\Neo4j\Command\CreateNode',
			'createRelationship' => 'Everyman\Neo4j\Command\CreateRelationship',
			'deleteIndex' => 'Everyman\Neo4j\Command\DeleteIndex',
			'deleteNode' => 'Everyman\Neo4j\Command\DeleteNode',
			'deleteRelationship' => 'Everyman\Neo4j\Command\DeleteRelationship',
			'executeCypherQuery' => 'Everyman\Neo4j\Command\ExecuteCypherQuery',
			'executeGremlinQuery' => 'Everyman\Neo4j\Command\ExecuteGremlinQuery',
			'executePagedTraversal' => 'Everyman\Neo4j\Command\ExecutePagedTraversal',
			'executeTraversal' => 'Everyman\Neo4j\Command\ExecuteTraversal',
			'getIndexes' => 'Everyman\Neo4j\Command\GetIndexes',
			'getNode' => 'Everyman\Neo4j\Command\GetNode',
			'getPaths' => 'Everyman\Neo4j\Command\GetPaths',
			'getNodeRelationships' => 'Everyman\Neo4j\Command\GetNodeRelationships',
			'getRelationshipTypes' => 'Everyman\Neo4j\Command\GetRelationshipTypes',
			'getRelationship' => 'Everyman\Neo4j\Command\GetRelationship',
			'getServerInfo' => 'Everyman\Neo4j\Command\GetServerInfo',
			'queryIndex' => 'Everyman\Neo4j\Command\QueryIndex',
			'removeFromIndex' => 'Everyman\Neo4j\Command\RemoveFromIndex',
			'saveIndex' => 'Everyman\Neo4j\Command\SaveIndex',
			'searchIndex' => 'Everyman\Neo4j\Command\SearchIndex',
			'updateNode' => 'Everyman\Neo4j\Command\UpdateNode',
			'updateRelationship' => 'Everyman\Neo4j\Command\UpdateRelationship',
		);
	}

	/**
	 * Return a named command object
	 *
	 * @param string $command
	 * @param array $args
	 * @return Command
	 * @throws Exception
	 */
	public function __call($command, $args)
	{
		if (!array_key_exists($command, $this->commandMap)) {
			throw new Exception("No command found mapped to $command");
		} else if (!is_callable($this->commandMap[$command])) {
			$generator = $this->commandMap[$command].'::factory';
			$this->commandMap[$command] = call_user_func($generator);
		}
		return call_user_func_array($this->commandMap[$command], $args);
	}

	/**
	 * Return the Neo4j REST endpoint
	 *
	 * @return string
	 */
	public function getEndpoint()
	{
		return "{$this->scheme}://{$this->host}:{$this->port}{$this->path}";
	}

	/**
	 * Encode data for transport
	 *
	 * @param mixed $data
	 * @return string
	 */
	public function encodeData($data)
	{
		$encoded = '';
		if (!is_scalar($data)) {
			if ($data) {
				$keys = array_keys($data);
				$nonNumeric = array_filter($keys, function ($var){
					return !is_int($var);
				});
				if ($nonNumeric) {
					$data = (object)$data;
				}
			} else {
				$data = (object)$data;
			}
		}

		$encoded = json_encode($data);
		return $encoded;
	}
	
	/**
	 * Make a request against the endpoint
	 * Returned array has the following elements:
	 *   'code' => the HTTP status code returned
	 *   'headers' => array of HTTP headers, indexed by header name
	 *   'data' => array return data
	 *
	 * @param string $method
	 * @param string $path
	 * @param array  $data
	 * @return array
	 */
	public function makeRequest($method, $path, $data=array())
	{
		$url = $this->getEndpoint().$path;

		$options = array(
			CURLOPT_URL => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HEADER => true,
			CURLOPT_HTTPHEADER => array(
				'Accept: application/json',
				'Content-type: application/json',
			),
			CURLOPT_CUSTOMREQUEST => self::GET,
			CURLOPT_POST => false,
			CURLOPT_POSTFIELDS => null,
		);

		if ($this->username && $this->password) {
			$options[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
			$options[CURLOPT_USERPWD] = $this->username.':'.$this->password;
		}

		switch ($method) {
			case self::DELETE :
				$options[CURLOPT_CUSTOMREQUEST] = self::DELETE;
				break;

			case self::POST :
				$dataString = $this->encodeData($data);
				$options[CURLOPT_CUSTOMREQUEST] = self::POST;
				$options[CURLOPT_POST] = true;
				$options[CURLOPT_POSTFIELDS] = $dataString;
				$options[CURLOPT_HTTPHEADER][] = 'Content-Length: '.strlen($dataString);
				break;

			case self::PUT :
				$dataString = $this->encodeData($data);
				$options[CURLOPT_CUSTOMREQUEST] = self::PUT;
				$options[CURLOPT_POSTFIELDS] = $dataString;
				$options[CURLOPT_HTTPHEADER][] = 'Content-Length: '.strlen($dataString);
				break;
		}

		$ch = $this->getHandle();
		curl_setopt_array($ch, $options);

		$response = curl_exec($ch);
		$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

		if (!$code) {
			$code = 500;
			$headerSize = 0;
			$response = json_encode(array("error"=>curl_error($ch).' ['.curl_errno($ch).']'));
		}

		$bodyString = substr($response, $headerSize);
		$bodyData = json_decode($bodyString, true);

		$headerString = substr($response, 0, $headerSize);
		$headers = explode("\r\n",$headerString);
		foreach ($headers as $i => $header) {
			unset($headers[$i]);
			$parts = explode(':',$header);
			if (isset($parts[1])) {
				$name = trim(array_shift($parts));
				$value = join(':',$parts);
				$headers[$name] = $value;
			}
		}

		return array(
			'code' => $code,
			'headers' => $headers,
			'data' => $bodyData,
		);
	}

	/**
	 * Make a GET request
	 *
	 * @param $path
	 * @param $data
	 * @return array see 'makeRequest'
	 */
	public function get($path, $data=array())
	{
		return $this->makeRequest(self::GET, $path, $data);
	}

	/**
	 * Make a POST request
	 *
	 * @param $path
	 * @param $data
	 * @return array see 'makeRequest'
	 */
	public function post($path, $data=array())
	{
		return $this->makeRequest(self::POST, $path, $data);
	}

	/**
	 * Make a PUT request
	 *
	 * @param $path
	 * @param $data
	 * @return array see 'makeRequest'
	 */
	public function put($path, $data=array())
	{
		return $this->makeRequest(self::PUT, $path, $data);
	}

	/**
	 * Make a DELETE request
	 *
	 * @param $path
	 * @return array see 'makeRequest'
	 */
	public function delete($path)
	{
		return $this->makeRequest(self::DELETE, $path);
	}

	/**
	 * Set username and password to use with HTTP Basic Auth
	 *
	 * Returns this Trnasport object
	 *
	 * @param string $username
	 * @param string $password
	 * @return Transport
	 */
	public function setAuth($username=null, $password=null)
	{
		$this->username = $username;
		$this->password = $password;
		return $this;
	}

	/**
	 * Turn HTTPS on or off
	 *
	 * Returns this Trnasport object
	 *
	 * @param boolean $useHttps
	 * @return Transport
	 */
	public function useHttps($useHttps=true)
	{
		$this->scheme = $useHttps ? 'https' : 'http';
		return $this;
	}

	/**
	 * Get the cURL handle
	 *
	 * @return resource cURL handle
	 */
	protected function getHandle()
	{
		if (!$this->handle) {
			$this->handle = curl_init();
		}
		return $this->handle;
	}
}
