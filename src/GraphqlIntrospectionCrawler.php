<?php

namespace rdx\imdb;

use Generator;
use GuzzleHttp\Cookie\CookieJar;
use RuntimeException;

class GraphqlIntrospectionCrawler {

	protected const PAGER = 4;

	protected string $_query;

	protected Client $client;
	/** @var list<string> */
	protected array $todo = [];
	/** @var array<string, AssocArray> */
	protected array $done = [];

	public function __construct(
		protected string $filepath,
		protected int $pager = self::PAGER,
	) {
		if (!is_file($filepath) || !is_writable($filepath)) {
			throw new RuntimeException(sprintf("Cache file %s isn't writable.", $filepath));
		}

		$this->client = new Client(new class implements Auth {
			public function cookies() : CookieJar {
				return new CookieJar();
			}
			public function logIn(Client $client) : bool {
				return true;
			}
		});

		$data = file_get_contents($filepath);
		$data = $data ? unserialize($data) : [];
		$this->todo = $data['todo'] ?? ['Query', 'Mutation'];
		$this->done = $data['done'] ?? [];
		$this->simplifyTypes($this->done);
	}

	/**
	 * @return AssocArray
	 */
	public function getSchema() : array {
		return [
			'directives' => [],
			'mutationType' => isset($this->done['Mutation']) ? ['name' => 'Mutation'] : null,
			'queryType' => isset($this->done['Query']) ? ['name' => 'Query'] : null,
			'subscriptionType' => null,
			'types' => array_values($this->done),
		];
	}

	/**
	 * @param AssocArray $in
	 * @param-out AssocArray $in
	 */
	protected function simplifyTypes(array &$in) : void {
		foreach ($in as &$node) {
			if (is_string($node['description'] ?? -1)) {
				$node['description'] = trim(explode('---------------------', $node['description'])[0]);
			}

			if (is_array($node)) {
				$this->simplifyTypes($node);
			}

			unset($node);
		}
	}

	public function crawl() : Generator {
		yield from [];

		while (count($this->todo)) {
			$typeNames = $this->takeTypes();
echo implode(' / ', $typeNames) . " ... ";
			$typeInfos = $this->fetchTypes($typeNames);
echo "OK\n";
			foreach ($typeInfos as $typeInfo) {
				$this->done[ $typeInfo['name'] ] = $typeInfo;
				$this->queueTypeNames($typeInfo);
			}

			$this->write();

			yield count($this->todo) => $typeNames;
// var_dump(count($this->todo));


// if (rand(0, 5) == 0) return;
		}

var_dump(count($this->done));
	}

	/**
	 * @return list<string>
	 */
	protected function takeTypes() : array {
		return array_values(array_splice($this->todo, 0, $this->pager, []));
	}

	/**
	 * @param list<string> $names
	 * @return list<AssocArray>
	 */
	protected function fetchTypes(array $names) : array {
		$query = $this->makeQuery($names);

		$data = $this->client->graphqlData($query);

		$typeInfos = [];
		for ($i = 0; $i < $this->pager; $i++) {
			$typeInfo = $data['data']['type' . $i] ?? null;
			if (!$typeInfo) {
				break;
			}

			$typeInfos[] = $typeInfo;
		}

		return $typeInfos;
	}

	/**
	 * @param AssocArray $node
	 */
	protected function queueTypeNames(array $node) : void {
		foreach ($node as $key => $value) {
			if (isset($value['kind'], $value['name'])) {
				$this->queueTypeName($value['name']);
			}

			if (is_array($value)) {
				$this->queueTypeNames($value);
			}
		}
	}

	protected function queueTypeName(string $name) : void {
		if (isset($this->done[$name])) return;
		if (in_array($name, $this->todo)) return;

		$this->todo[] = $name;
	}

	/**
	 * @param list<string> $types
	 */
	protected function makeQuery(array $types) : string {
		$query = [];
		$query[] = 'query JustType {';
		foreach ($types as $i => $name) {
			$query[] = "\t" . 'type' . $i . ': __type(name: "' . $name . '") {';
			$query[] = "\t\t" . '...FullType';
			$query[] = "\t" . '}';
		}
		$query[] = '}';
		return implode("\n", $query) . "\n\n" . $this->getQuery();
	}

	protected function getQuery() : string {
		return $this->_query ??= (string) file_get_contents(__DIR__ . '/introspection-alt.graphql');
	}

	protected function write() : void {
		$data = serialize([
			'todo' => $this->todo,
			'done' => $this->done,
		]);
		file_put_contents($this->filepath, $data);
	}

}
