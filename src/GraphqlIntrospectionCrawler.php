<?php

namespace rdx\imdb;

use Generator;
use GuzzleHttp\Cookie\CookieJar;
use RuntimeException;

class GraphqlIntrospectionCrawler {

	protected const PAGER = 4;

	/** @var list<string> */
	protected const BUILTIN_SCALARS = ['String', 'Int', 'Float', 'Boolean', 'ID'];

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
	public function getSchema(bool $descriptions = true) : array {
		$types = array_values($this->done);
		if (!$descriptions) {
			$this->removeDescriptions($types);
		}

		return [
			'directives' => [],
			'mutationType' => isset($this->done['Mutation']) ? ['name' => 'Mutation'] : null,
			'queryType' => isset($this->done['Query']) ? ['name' => 'Query'] : null,
			'subscriptionType' => null,
			'types' => $types,
		];
	}

	/**
	 * @param array<mixed> $in
	 * @param-out array<mixed> $in
	 */
	protected function removeDescriptions(array &$in) : void {
		foreach ($in as &$node) {
			if (is_array($node)) {
				if (array_key_exists('description', $node)) {
					$node['description'] = null;
				}

				$this->removeDescriptions($node);
			}

			unset($node);
		}
	}

	public function getSDL(bool $descriptions = true) : string {
		$types = $this->getSchema($descriptions)['types'];
		usort($types, fn($a, $b) => $a['name'] <=> $b['name']);

		$blocks = [];
		foreach ($types as $type) {
			$block = match ($type['kind']) {
				'SCALAR' => $this->printScalar($type),
				'OBJECT' => $this->printFielded('type', $type),
				'INTERFACE' => $this->printFielded('interface', $type),
				'UNION' => $this->printUnion($type),
				'ENUM' => $this->printEnum($type),
				'INPUT_OBJECT' => $this->printInputObject($type),
				default => null,
			};

			if ($block !== null) {
				$blocks[] = $block;
			}
		}

		return implode("\n\n", $blocks) . "\n";
	}

	/**
	 * @param AssocArray $type
	 */
	protected function printScalar(array $type) : ?string {
		if (in_array($type['name'], self::BUILTIN_SCALARS, true)) {
			return null;
		}

		return $this->printDescription($type['description']) . 'scalar ' . $type['name'];
	}

	/**
	 * @param AssocArray $type
	 */
	protected function printFielded(string $keyword, array $type) : string {
		$header = $keyword . ' ' . $type['name'];
		if (!empty($type['interfaces'])) {
			$interfaceNames = array_column($type['interfaces'], 'name');
			$header .= ' implements ' . implode(' & ', $interfaceNames);
		}

		$lines = [];
		foreach ($type['fields'] as $field) {
			$lines[] = $this->printField($field);
		}

		return $this->printBlock($type['description'], $header, $lines);
	}

	/**
	 * @param AssocArray $field
	 */
	protected function printField(array $field) : string {
		$description = $this->printDescription($field['description'], "\t");
		$args = $this->printArgs($field['args']);
		$type = $this->printTypeRef($field['type']);
		$deprecated = $this->printDeprecated($field);

		return $description . "\t" . $field['name'] . $args . ': ' . $type . $deprecated;
	}

	/**
	 * @param list<AssocArray> $args
	 */
	protected function printArgs(array $args) : string {
		if (!$args) {
			return '';
		}

		$descriptions = array_column($args, 'description');
		if (!array_filter($descriptions)) {
			$parts = [];
			foreach ($args as $arg) {
				$parts[] = $this->printInputValue($arg);
			}

			return '(' . implode(', ', $parts) . ')';
		}

		$lines = [];
		foreach ($args as $arg) {
			$description = $this->printDescription($arg['description'], "\t\t");
			$lines[] = $description . "\t\t" . $this->printInputValue($arg);
		}

		return "(\n" . implode("\n", $lines) . "\n\t)";
	}

	/**
	 * @param AssocArray $type
	 */
	protected function printUnion(array $type) : string {
		$memberNames = array_column($type['possibleTypes'], 'name');
		$members = implode(' | ', $memberNames);

		return $this->printDescription($type['description']) . 'union ' . $type['name'] . ' = ' . $members;
	}

	/**
	 * @param AssocArray $type
	 */
	protected function printEnum(array $type) : string {
		$lines = [];
		foreach ($type['enumValues'] as $value) {
			$description = $this->printDescription($value['description'], "\t");
			$lines[] = $description . "\t" . $value['name'] . $this->printDeprecated($value);
		}

		return $this->printBlock($type['description'], 'enum ' . $type['name'], $lines);
	}

	/**
	 * @param AssocArray $type
	 */
	protected function printInputObject(array $type) : string {
		$lines = [];
		foreach ($type['inputFields'] as $field) {
			$description = $this->printDescription($field['description'], "\t");
			$lines[] = $description . "\t" . $this->printInputValue($field);
		}

		return $this->printBlock($type['description'], 'input ' . $type['name'], $lines);
	}

	/**
	 * @param list<string> $lines
	 */
	protected function printBlock(?string $description, string $header, array $lines) : string {
		$body = implode("\n", $lines);

		return $this->printDescription($description) . $header . " {\n" . $body . "\n}";
	}

	/**
	 * @param AssocArray $value
	 */
	protected function printInputValue(array $value) : string {
		$line = $value['name'] . ': ' . $this->printTypeRef($value['type']);
		if ($value['defaultValue'] !== null) {
			$line .= ' = ' . $value['defaultValue'];
		}

		return $line;
	}

	/**
	 * @param AssocArray $ref
	 */
	protected function printTypeRef(array $ref) : string {
		return match ($ref['kind']) {
			'NON_NULL' => $this->printTypeRef($ref['ofType']) . '!',
			'LIST' => '[' . $this->printTypeRef($ref['ofType']) . ']',
			default => $ref['name'],
		};
	}

	/**
	 * @param AssocArray $field
	 */
	protected function printDeprecated(array $field) : string {
		if (empty($field['isDeprecated'])) {
			return '';
		}

		if ($field['deprecationReason'] === null) {
			return ' @deprecated';
		}

		$reason = json_encode($field['deprecationReason'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

		return ' @deprecated(reason: ' . $reason . ')';
	}

	protected function printDescription(?string $description, string $indent = '') : string {
		if (!$description) {
			return '';
		}

		$lines = explode("\n", $description);
		$lines = ['"""', ...$lines, '"""'];

		$out = '';
		foreach ($lines as $line) {
			$out .= $indent . $line . "\n";
		}

		return $out;
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
