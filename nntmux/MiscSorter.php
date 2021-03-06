<?php
namespace nntmux;

use ApaiIO\ResponseTransformer\XmlToSimpleXmlObject;
use App\Models\Settings;
use nntmux\db\DB;
use ApaiIO\Configuration\GenericConfiguration;
use ApaiIO\ApaiIO;
use ApaiIO\Operations\Lookup;


/**
 * Class MiscSorter
 */
class MiscSorter
{

	const PROC_SORTER_NONE = 0; //Release has not been run through MiscSorter before
	const PROC_SORTER_DONE = 1; //Release has been processed by MiscSorter

	/**
	 * @var int
	 */
	private $qty;

	/**
	 * @var bool
	 */
	private $echooutput;

	/**
	 * @var bool
	 */
	private $debugging;

	/**
	 * @var DB
	 */
	private $pdo;

	/**
	 * @var Movie
	 */
	private $movie;

	/**
	 * @var Music
	 */
	private $music;

	/**
	 * @var array|bool|string
	 */
	public $pubkey;

	/**
	 * @var array|bool|string
	 */
	public $privkey;

	/**
	 * @var array|bool|string
	 */
	public $asstag;

	/**
	 * @var Books
	 */
	private $book;

	/**
	 * @param bool $echooutput
	 * @param      $pdo
	 *
	 * @throws \Exception
	 */
	public function __construct($echooutput = false, &$pdo)
	{
		$this->echooutput = (NN_ECHOCLI && $echooutput);

		$this->pdo = ($pdo instanceof DB ? $pdo : new DB());
		$this->movie = new Movie(['Echo' => $this->echooutput, 'Settings' => $this->pdo]);
		$this->music = new Music(['Echo' => $this->echooutput, 'Settings' => $this->pdo]);
		$this->book = new Books(['Echo' => $this->echooutput, 'Settings' => $this->pdo]);
		$this->pubkey = Settings::value('APIs..amazonpubkey');
		$this->privkey = Settings::value('APIs..amazonprivkey');
		$this->asstag = Settings::value('APIs..amazonassociatetag');
	}

	// Main function that determines which operation(s) should be run based on the releases NFO file
	/**
	 * @param int|string $category
	 * @param int $id
	 *
	 * @return bool
	 */
	public function nfosorter($category, $id)
	{
		$idarr = ($id !== '' ? sprintf('AND r.id = %d', $id) : '');
		$cat = ($category === '' ? sprintf('AND r.categories_id = %d', Category::OTHER_MISC) : sprintf('AND r.categories_id = %d', $category));

		$res = $this->pdo->queryDirect(
			sprintf('
							SELECT UNCOMPRESS(rn.nfo) AS nfo,
								r.id, r.name, r.searchname
							FROM release_nfos rn
							INNER JOIN releases r ON rn.releases_id = r.id
							INNER JOIN groups g ON r.groups_id = g.id
							WHERE rn.nfo IS NOT NULL
							AND r.proc_sorter = %d
							AND r.predb_id = 0 %s',
				self::PROC_SORTER_NONE,
				$idarr = '' ? $cat : $idarr
			)
		);

		if ($res instanceof \Traversable) {

			foreach ($res as $row) {

				if (strlen($row['nfo']) > 100) {

					$nfo = utf8_decode($row['nfo']);

					unset($row['nfo']);
					$matches = $this->_sortTypeFromNFO($nfo);

					array_shift($matches);
					$matches = $this->doarray($matches);

					foreach ($matches as $m) {

						$case = (isset($m) ? str_replace(' ', '', $m) : '');

						if (in_array($m, ['os', 'platform', 'console'], false) && preg_match('/(?:\bos\b(?: type)??|platform|console)[ \.\:\}]+(\w+?).??(\w*?)/iU', $nfo, $set)) {
							if (is_array($set)) {
								if (isset($set[1])) {
									$case = strtolower($set[1]);
								} else	if (isset($set[2]) && strlen($set[2]) > 0 && (stripos($set[2], 'mac') !== false || stripos($set[2], 'osx') !== false)) {
									$case = strtolower($set[2]);
								} else {
									$case = str_replace(' ', '', $m);
								}
							}
						}

						$pos = $this->nfopos($this->_cleanStrForPos($nfo), $this->_cleanStrForPos($m));

						if ($pos !== false && $pos > 0.55 && $case !== 'imdb') {
							break;
						}

						if ($ret = $this->matchnfo($case, $nfo, $row)) {
							return $ret;
						}
					}
				}
			}
		}
		$this->_setProcSorter(self::PROC_SORTER_DONE, $id);
		return false;
	}

	/**
	 * @param $nfo
	 * @param $str
	 *
	 * @return bool|float|int
	 */
	private function nfopos($nfo, $str)
	{
		$pos = stripos($nfo, $str);
		if ($pos !== false) {
			return $pos / strlen($nfo);
		}

		return false;

	}

	/**
	 * @param $str
	 *
	 * @return mixed
	 */
	private function _cleanStrForPos($str)
	{
		$str = str_replace([' ', '  ',  '\t', '_', '.', '?'], ' ', $str);
		$str = preg_replace('/^\s+?/Umi', '', $str);
		return $str;
	}

	/**
	 * @param $matches
	 *
	 * @return array
	 */
	private function doarray($matches): array
	{
		$r = [];
		$i = 0;

		$matches = array_count_values($matches);
		$matches = array_change_key_case($matches, CASE_LOWER);

		foreach ($matches as $m => $v) {
			$x = -1;

			if (strlen($m) < 50) {
				$str = preg_replace("/\s/iU", '', $m);

				$m = strtolower($str);

				$x = 0;

				if ($m === 'imdb') {
					$x = -11;
				} else if ($m ==='anidb.net') {
					$x = -10;
				} else if ($m === 'upc') {
					$x = -9;
				} else if ($m === 'amazon.') {
					$x = -8;
				} else if ($m === 'asin' || $m === 'isbn') {
					$x = -7;
				} else if ($m === 'tvrage') {
					$x = -6;
				} else if ($m === 'audiobook') {
					$x = -5;
				} else if ($m === 'os') {
					$x = -4;
				} else if (in_array($m, ['mac', 'macintosh', 'dmg', 'macos', 'macosx', 'osx'], false)) {
					$x = -3;
				} else if ($m === 'itunes.apple.com/') {
					$x = -2;
				} else if (in_array($m, ['documentaries', 'documentary', 'doku'], false)) {
					$x = -1;
				} else if (preg_match('/sport|deportes|nhl|nfl|\bnba/i', $m)) {
					$x = 1000;
				} else if (preg_match('/avi|xvid|divx|mkv/i', $m)) {
					$x = 1001;
				} else if (preg_match('/\.(?:rar|001)/i', $m)) {
					$x = 1002;
				} else if (stripos($m, 'pdf') !== false) {
					$x = 1003;
				}
			}

			if ($x !== -1) {
				if ($x === 0) {
					$r[$i++] = $m;
				} else if (isset($r[$x])) {
					$r[$x + random_int(0, 100) / 100] = $m;
				} else {
					$r[$x] = $m;
				}
			}
		}
		ksort($r);
		$r = array_values($r);
		return $r;
	}

	/**
	 * This function cleans the release name before updating
	 *
	 * @param string $name
	 * @return string $name
	 */
	private function cleanname($name): string
	{
		do {
			$original = $name;
			$name = preg_replace('/[\{\[\(]\d+[ \.\-\/]+\d+[\]\}\)]/iU', ' ', $name);
			$name = preg_replace('/[\x01-\x1f\!\?\[\{\}\]\/\:\|]+/iU', ' ', $name);
			$name = str_replace('  ', ' ', $name);
			$name = preg_replace('/^[\s\.]+|[\s\.]{2,}$/iU', '', $name);
			$name = str_replace(' - - ', ' - ', $name);
			$name = preg_replace('/^[\s\-\_\.]/iU', '', $name);
			$name = trim($name);
		} while ($original !== $name);

		return mb_strimwidth($name, 0, 255);
	}

	/**
	 * @param int    $id
	 * @param string $name
	 * @param int    $typeid
	 * @param string $type
	 *
	 * @return bool
	 */
	private function dodbupdate($id = 0, $name = '', $typeid = 0, $type = ''): bool
	{
		$nameChanged = false;

		$release = $this->pdo->queryOneRow(
			sprintf('
							SELECT r.id AS releases_id, r.searchname AS searchname,
								r.name AS name, r.fromname, r.categories_id, r.groups_id
							FROM releases r
							WHERE r.id = %d',
				$id
			)
		);

		if ($release !== false && is_array($release) && $name !== '' && $name !== $release['searchname'] && strlen($name) >= 10) {
			(new NameFixer(['Settings' => $this->pdo]))->updateRelease($release, $name, $type, true, 'sorter ', 1, 1);
			$nameChanged = true;
		} else {
			$this->_setProcSorter(self::PROC_SORTER_DONE, $id);
		}

		if ($type !== '' && in_array($type, ['bookinfo_id', 'consoleinfo_id', 'imdbid', 'musicinfo_id'], false)) {
			$this->pdo->queryExec(
				sprintf('
								UPDATE releases
								SET %s = %d
								WHERE id = %d',
					$type,
					$typeid,
					$id
				)
			);
		}
		return $nameChanged;
	}

	/**
	 * @param string $nfo
	 * @param int    $id
	 *
	 * @return bool
	 */
	private function doOS($nfo = '', $id = 0): bool
	{
		$ok = false;
		$tmp = [];

		$nfo = preg_replace('/[^\x09-\x80]|\?/', '', $nfo);
		$nfo = preg_replace('/[\x01-\x09\x0e-\x20]/', ' ', $nfo);

		$cleanNfo = $this->_cleanStrForPos($nfo);

		$pattern = '/(?<!fine[ \-\.])(?:\btitle|\bname|release)\b(?![ \-\.]type|[ \-\.]info(?:rmation)?|[ \-\.]date|[ \-\.]name|[ \-\.]notes)(?:[\-\:\.\}\[\s]+?) ?([a-z0-9\.\- \(\)\']+?)/Ui';
		$set = $this->_doOSpregSplit($pattern, $cleanNfo);

		if (!isset($set[1]) || strlen($set[1]) < 3) {
			$pattern = '/(?:(?:presents?|p +r +e +s +e +n +t +s)(?:[^a-z0-9]+?))([a-z0-9 \.\-\_\']+?)/Ui';
			$set = $this->_doOSpregSplit($pattern, $cleanNfo);
		}

		if (isset($set[1])) {
			if (preg_match('/^(.+)(\(c\)|\xA9)/i', $set[1], $tmp)) {
				$set[1] = $tmp[1];
			}
			if (strlen($set[1]) < 128 && !preg_match('/(another)? *(fine)? *release/i', $set[1])) {
				$ok = $this->dodbupdate($id, $this->cleanname($set[1]), null, "app");
			}
		}

		return $ok;
	}

	/**
	 * @param string $pattern
	 * @param string $nfo
	 *
	 * @return array
	 */
	private function _doOSpregSplit($pattern = '', $nfo = ''): array
	{
		return preg_split($pattern, $nfo, 0, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
	}

	/**
	 * @param string $nfo
	 * @param int    $imdb
	 * @param string $name
	 *
	 * @return string
	 */
	private function moviename($nfo = '', $imdb = 0, $name = ''): string
	{
		$tmp = [];

		$qual = $this->_getVideoQuality($nfo);

		//Clean up the name
		$name = preg_replace('/[a-f0-9]{10,}/i', ' ', $name);
		$name = str_replace('\\', ' ', $name);

		$name1 = str_replace(['  ', '--', '\_\_'], ' ', trim($name));

		if ($imdb > 0) {
			$movie = $this->movie->getMovieInfo($imdb);
			if ($movie !== false) {
				$name2 = '';
				$word = '/' . $movie['title'] . ' ' . $movie['year'] . '/i';
				$tmp[] = preg_split($word, $name1);
				if ($tmp instanceof \Traversable) {
					foreach ($tmp as $t) {
						$name2 .= ' ' . $t[1];
					}
				}
				$name1 = $name2;
			}
		}

		$retName = (isset($movie) && $qual !== false ? $movie['title'] . '.' . $movie['year'] . '.' . $name1 . '.' . $qual : $name1);
		return trim($retName);
	}

	/**
	 * @param string $nfo
	 *
	 * @return bool|mixed
	 */
	private function _getVideoQuality($nfo = '')
	{
		$qualities = ['(:?..)?tv', '480[ip]?', '640[ip]?', '720[ip]?', '1080[ip]?', 'ac3', 'audio_ts', 'avi', 'bd[\- ]?rip', 'bd25', 'bd50',
					  'bdmv', 'blu ?ray', 'br[\- ]?disk', 'br[\- ]?rip', 'cam', 'cam[\- ]?rip', 'dc', 'directors.?cut', 'divx\d?', 'dts', 'dvd', 'dvd[\- ]?r',
					  'dvd[\- ]?rip', 'dvd[\- ]?scr', 'extended', 'hd', 'hd[\- ]?tv', 'h264', 'hd[\- ]?cam', 'hd[\- ]?ts', 'iso', 'm2ts', 'mkv', 'mpeg(:?\-\d)?',
					  'mpg', 'ntsc', 'pal', 'proper', 'ppv', 'ppv[\- ]?rip', 'r\d{1}', 'repack', 'repacked', 'scr', 'screener', 'tc', 'telecine', 'telesync', 'ts',
					  'tv[\- ]?rip', 'unrated', 'vhs( ?rip)?', 'video_ts', 'video ts', 'x264', 'xvid', 'web[\- ]?rip'];

		foreach ($qualities as $quality) {
			if (stripos($nfo, $quality) !== false) {
				return $quality;
			}
		}
		return false;
	}

	/**
	 * @param string $name
	 * @param int    $id
	 * @param string $nfo
	 * @param        $q
	 * @param string $region
	 * @param bool   $case
	 * @param string $row
	 *
	 * @return bool
	 */
	private function doAmazon($name = '', $id = 0, $nfo = "", $q, $region = 'com', $case = false, $row = ''): bool
	{
		$conf = new GenericConfiguration();
		try {
			$conf
				->setCountry($region)
				->setAccessKey($this->pubkey)
				->setSecretKey($this->privkey)
				->setAssociateTag($this->asstag)
				->setResponseTransformer(new XmlToSimpleXmlObject());
		} catch (\Exception $e) {
			echo $e->getMessage();
		}

		$amalookup = new Lookup();

		$apaiIo = new ApaiIO($conf);
		$ok = false;

		try {

			switch ($case) {
				case 'upc':
					$amalookup->getName();
					$amalookup->setItemId(trim($q));
					$amalookup->setSearchIndex($region);
					$amalookup->setIdType('UPC');
					$amalookup->setResponseGroup(['Medium']);
					$response = $apaiIo->runOperation($amalookup);
					break;
				case 'asin':
					$amalookup->getName();
					$amalookup->setItemId(trim($q));
					$amalookup->setResponseGroup(['Medium']);
					$response = $apaiIo->runOperation($amalookup);
					break;
				case 'isbn':
					$amalookup->getName();
					$amalookup->setItemId(trim($q));
					$amalookup->setSearchIndex($region);
					$amalookup->setIdType('ISBN');
					$amalookup->setResponseGroup(['Medium']);
					$response = $apaiIo->runOperation($amalookup);
					break;
				default:
					$response = false;
			}

		} catch (\Exception $e) {
			echo 'Caught exception: ', $e->getMessage() . PHP_EOL;
		}

		if (isset($response, $response->Items->Item)) {
			$type = $response->Items->Item->ItemAttributes->ProductGroup;
			switch ($type) {
				case 'Audible':
				case 'Book':
				case 'eBooks':
					$ok = $this->_doAmazonBooks($response, $id);
					break;
				case 'Digital Music Track':
				case 'Digital Music Album':
				case 'Music':
					$ok = $this->_doAmazonMusic($response, $id);
					break;
				case 'Bluray':
				case 'Movies':
				case 'DVD':
				case 'DVD & Bluray':
					$ok = $this->_doAmazonMovies($response, $id, $nfo);
					break;
				case 'Video Games':
					$ok = $this->_doAmazonVG($response, $id);
					break;
				default:
					echo PHP_EOL . ColorCLI::error("Amazon category $type could not be parsed for " . $name) . PHP_EOL;
			}
		}

		return $ok;
	}

	/**
	 * Main switch for determining operation type after parsing the NFO file
	 *
	 *
	 * @param array $response
	 * @param int   $id
	 *
	 * @return bool
	 * @throws \Exception
	 */
	private function _doAmazonBooks(array $response = [], $id = 0): bool
	{
		$audiobook = false;
		$v = (string)$response->Items->Item->ItemAttributes->Format;
		if (stripos($v, 'audiobook') !== false) {
			$audiobook = true;
		}
		$new = (string)$response->Items->Item->ItemAttributes->Author;
		$name = $new . ' - ' . (string)$response->Items->Item->ItemAttributes->Title;

		$rel = $this->_doAmazonLocal('bookinfo', (string)$response->Items->Item->ASIN);

		if (count($rel) === 0) {
			$bookId = $this->book->updateBookInfo('', $response);
			unset($book);
		} else {
			$bookId = $rel['id'];
		}

		if ($audiobook) {
			$ok = $this->dodbupdate($id, $name, $bookId, 'bookinfo_id');
		} else {
			$ok = $this->dodbupdate($id, $name, $bookId, 'bookinfo_id');
		}

		return $ok;
	}

	/**
	 * @param string $table
	 * @param string $asin
	 *
	 * @return array|bool
	 */
	private function _doAmazonLocal($table = '', $asin = '')
	{
		return $this->pdo->queryOneRow(
			sprintf('
						SELECT id
						FROM %s
						WHERE asin = %s',
				$table,
				$this->pdo->escapeString($asin)
			)
		);
	}


	/**
	 * @param array $response
	 * @param int   $id
	 *
	 * @return bool
	 */
	private function _doAmazonMusic($response = [], $id = 0)
	{
		$new = (string)$response->Items->Item->ItemAttributes->Artist;
		if ($new !== '') {
			$new .= ' - ';
		}
		$name = $new . (string)$response->Items->Item->ItemAttributes->Title;

		$rel = $this->_doAmazonLocal('musicinfo', (string)$response->Items->Item->ASIN);

		if ($rel !== false) {
			$ok = $this->dodbupdate($id, $name, $rel['id'], 'musicinfo_id');
		} else {
			$musicId = $this->music->updateMusicInfo('', '', $response);
			$ok = $this->dodbupdate($id, $name, $musicId, 'musicinfo_id');
		}

		return $ok;
	}

	// tries to derive author and title of book from release NFO

	/**
	 * @param array $response
	 * @param int   $id
	 * @param string $nfo
	 *
	 * @return bool
	 */
	private function _doAmazonMovies(array $response = [], $id = 0, $nfo): bool
	{
		$new = (string)$response->Items->Item->ItemAttributes->Title;
		$new = $new . ' (' . substr((string)$response->Items->Item->ItemAttributes->ReleaseDate, 0, 4) . ')';
		$name = $this->moviename($nfo, 0, $new);

		return $this->dodbupdate($id, $name, null, 'amazonMov');
	}

	/**
	 * @param array $response
	 * @param int   $id
	 *
	 * @return bool
	 */
	private function _doAmazonVG($response = [], $id = 0)
	{
		$name = (string)$response->Items->Item->ItemAttributes->Title;
		$name .= '.' . (string)$response->Items->Item->ItemAttributes->Region . '.';
		$name .= '-' . (string)$response->Items->Item->ItemAttributes->Platform;

		$rel = $this->_doAmazonLocal('consoleinfo', (string)$response->Items->Item->ASIN);

		if ($rel !== false) {
			$ok = $this->dodbupdate($id, $name, $rel['id'], 'consoleinfo_id');
		} else {
			$consoleId = $this->console->
			updateConsoleInfo([
					'title'    => (string)$response->Items->Item->Title,
					'node'     => (int)$response->Items->Item->BrowseNodes->BrowseNodeId,
					'platform' => (string)$response->Items->Item->ItemAttributes->Platform
				]
			);
			$ok = $this->dodbupdate($id, $name, $consoleId, 'consoleinfo_id');
		}

		return $ok;
	}

	/**
	 * @param $case
	 * @param string $nfo
	 * @param $row
	 *
	 * @return bool
	 */
	private function matchnfo($case, $nfo, $row)
	{
		$ok = false;

		switch (strtolower($case)) {
			case 't r a c k':
			case 'track':
			case 'trax':
			case 'lame':
			case 'album':
			case 'music':
			case '44.1kHz':
			case 'm3u':
			case 'flac':
				$ok = $this->_matchNfoAudio($nfo, $row);
				break;
			case 'dmg':
			case 'mac':
			case 'macintosh':
			case 'macos':
			case 'macosx':
			case 'osx':
			case 'windows':
			case 'win':
			case 'winall':
			case 'winxp':
			case 'plugin':
			case 'crack':
			case 'linux':
			case 'install':
			case 'application':
			case 'android':
			case 'ios':
			case 'iphone':
			case 'ipad':
			case 'ipod':
				$ok = $this->doOS($nfo, $row['id']);
				break;
			case 'game':
				$set = preg_split('/\>(.*)\</U', $nfo, 0, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
				if (isset($set[1])) {
					$ok = $this->dodbupdate($row['id'], $this->cleanname($set[1]));
				} else {
					$ok = $this->doOS($nfo, $row['id']);
				}
				break;
			case 'imdb':
				$ok = $this->_matchNfoImdb($nfo, $row);
				break;
			case 'audiobook':
			case 'audible':
			case 'recordedbooks':
			case 'spokenbook':
			case 'readby':
			case 'narratedby':
			case 'narrator':
			case 'speech':
				$ok = $this->_matchNfoBook($nfo, $row);
				break;
			case 'comicbook':
			case 'comix':
				$ok = $this->dodbupdate($row['id'], $this->cleanname($row['searchname']));
				break;
			case "asin":
			case "isbn":
				if (preg_match('/(?:isbn|asin)[ \:\.=]*? *?([a-zA-Z0-9\-\.]{8,20}?)/iU', $nfo, $set)) {
					$set[1] = str_replace(['-', '.'], '', $set[1]);

					if (strlen($set[1]) <= 13) {
						$set[2] = $set[1];
						$set[1] = 'com';
						$ok = $this->doAmazon($row['name'], $row['id'], $nfo, $set[2], $set[1], $case, $row);
					}
				}
				break;
			case 'amazon.':
				if (preg_match('/amazon\.([a-z]*?\.?[a-z]{2,3}?)\/.*\/dp\/([a-zA-Z0-9]{8,10}?)/iU', $nfo, $set)) {
					$ok = $this->doAmazon($row['name'], $row['id'], $nfo, $set[2], $set[1], 'asin', $row);
				}
				break;
			case 'upc':
				if (preg_match('/UPC\:?? *?([a-zA-Z0-9]*?)/iU', $nfo, $set)) {
					$set[2] = $set[1];
					$set[1] = 'All';
					$ok = $this->doAmazon($row['name'], $row['id'], $nfo, $set[2], $set[1], $case, $row);
				}
				break;
		}

		return $ok;
	}

	/**
	 * Tries to derive artist and title of album/song from release NFO
	 *
	 * @param string $nfo
	 * @param $row
	 *
	 * @return bool
	 */
	private function _matchNfoAudio($nfo, $row): bool
	{
		if (preg_match('/(a\s?r\s?t\s?i\s?s\s?t|l\s?a\s?b\s?e\s?l|mp3|e\s?n\s?c\s?o\s?d\s?e\s?r|rip|stereo|mono|single charts)/i', $nfo)
			&& !preg_match('/(\bavi\b|x\.?264|divx|mvk|xvid|install(?!ation)|Setup\.exe|unzip|unrar)/i', $nfo)
		) {
			$artist = preg_split('/(?:a\s?r\s?t\s?i\s?s\s?t\s?s?\b[^ \.\:]*|a\s?u\s?t\s?h\s?o\s?r\s?s?\b[^ \.\:]*) *?(?!(?:[^\s\.\:\}\]\*\x{2500}-\x{3000}\?] ?){2,}?\b)(?:[\*\?\-\=\|\;\:\.\[\}\]\(\s\x{2500}-\x{3000}\?]+?)[\s\.\>\:\(\)\x{2500}-\x{3000}\?]((?!\:) ?\w.+)(?:\n|$|\s{3}|\.{3})/Uuim', $nfo, 0, PREG_SPLIT_DELIM_CAPTURE);
			if(isset($artist[1])) {
				$title = preg_split('/(?:t+\s?i+\s?t+\s?l+\s?e+\b|a\s?l\s?b\s?u\s?m\b|r\s?e\s?l\s?e\s?a\s?s\s?e\b) *?(?!(?:[^\s\.\:\}\]\*\x{2500}-\x{3000}\?] ?){2,}?\b)(?:[\*\?\-\=\|\;\:\.\[\}\]\(\s\x{2500}-\x{3000}\?]+?)[\s\.\>\:\(\)\x{2500}-\x{3000}\?]((?!\:) ?\w.+)(?:\n|$|\s{3}|\.{3})/Uuim', $nfo, 0, PREG_SPLIT_DELIM_CAPTURE);
			}

			if (!isset($title[1], $artist[1])) {
				if (preg_match('/presents[\W\. \xb0-\x{3000}]+? ([^\-]+?) \- ([a-z0-9]?(?!\:).+(?:\s\s\s))/iuUm', $nfo, $matches)) {
					$artist[1] = $matches[1];
					$title[1] = $matches[2];
				}
				if (!isset($matches[2]) && preg_match('/[\h\_\.\:\xb0-\x{3000}]{2,}?([a-z].+) \- (.+?)(?:[\?\s\_\.\:\xb0-\x{3000}]{2,}|$)/Uiu', $nfo, $matches)) {
					$pos = $this->nfopos($this->_cleanStrForPos($nfo), $this->_cleanStrForPos($matches[1] . ' - ' . $matches[2]));
					if ($pos !== false && $pos < 0.45 && !preg_match('/\:\d\d$/', $matches[2]) && strlen($matches[1]) < 48 && strlen($matches[2]) < 64
						&& strpos('title', $matches[1]) === false && strpos('title', $matches[2]) === false
					) {
						$artist[1] = $matches[1];
						$title[1] = $matches[2];
					}
				}
			}
			if (!empty($artist[1])) {
				$artist[1] = $artist[3];
			}
			if (isset($title[1],$artist[1])) {
				return $this->dodbupdate($row['id'], $this->cleanname($artist[1] . ' - ' . $title[1]), null, 'audioNFO');
			}
		}

		return false;
	}

	/**
	 * Tries to derive the IMDB ID from release
	 *
	 * @param string $nfo
	 * @param $row
	 *
	 * @return bool
	 */
	private function _matchNfoImdb($nfo, $row): bool
	{
		$imdb = $this->movie->doMovieUpdate($nfo, 'sorter', $row['id']);
		if (isset($imdb) && $imdb > 0) {
			return $this->dodbupdate($row['id'], $this->moviename($row['id'], $row['searchname']), $imdb, 'imdbid');
		}
		return false;
	}

	/**
	 * Tries to derive author and title of book from release NFO
	 *
	 * @param string $nfo
	 * @param $row
	 *
	 * @return bool
	 */
	private function _matchNfoBook($nfo, $row): bool
	{
		$author = preg_split('/(?:a\s?u\s?t\s?h\s?o\s?r\b)+? *?(?!(?:[^\s\.\:\}\]\*\xb0-\x{3000}\?] ?){2,}?\b)(?:[\*\?\-\=\|\;\:\.\[\}\]\(\s\xb0-\x{3000}\?]+?)[\s\.\>\:\(\)]((?!\:) ?[a-z0-9\&].+)(?:\s\s\s|$|\.\.\.)/Uuim', $nfo, 0, PREG_SPLIT_DELIM_CAPTURE);
		$title = preg_split('/(?:t\s?i\s?t\s?l\s?e\b|b\s?o\s?o\s?k\b)+? *?(?!(?:[^\s\.\:\}\]\*\xb0-\x{3000}\?] ?){2,}?\b)(?:[\*\?\-\=\|\;\:\.\[\}\]\(\s\xb0-\x{3000}\?]+?)[\s\.\>\:\(\)]((?!\:) ?[a-z0-9\&].+)(?:\s\s\s|$|\.\.\.)/Uuim', $nfo, 0, PREG_SPLIT_DELIM_CAPTURE);

		if (isset($author[1], $title[1])) {
			return $this->dodbupdate($row['id'], Category::MUSIC_AUDIOBOOK, $this->cleanname($author[1] . ' - ' . $title[1]));
		} else if (preg_match('/[\h\_\.\:\xb0-\x{3000}]{2,}?([a-z].+) \- (.+)(?:[\s\_\.\:\xb0-\x{3000}]{2,}|$)/iu', $nfo, $matches)) {
			$pos = $this->nfopos($this->_cleanStrForPos($nfo), $this->_cleanStrForPos($matches[1] . ' - ' . $matches[2]));
			if ($pos !== false && $pos < 0.4 && !preg_match('/\:\d\d$/', $matches[2]) && strlen($matches[1]) < 48 && strlen($matches[2]) < 48
				&& strpos('title', $matches[1]) === false && strpos('title', $matches[2]) === false) {
				return $this->dodbupdate($row['id'], $this->cleanname($matches[1] . ' - ' . $matches[2]), null, 'bookNFO');
			}
		}
		return false;
	}

	/**
	 * Sets the release to its proper status in the database
	 *
	 * @param int $status
	 * @param int $id
	 */
	private function _setProcSorter($status = 0, $id = 0): void
	{
		$this->pdo->queryExec(
			sprintf('
						UPDATE releases
						SET proc_sorter = %d
						WHERE id = %d',
				$status,
				$id
			)
		);
	}

	/**
	 * Derives type of processing to do by preg_splitting NFO file and returning the results of the split
	 *
	 * @param string $nfo
	 *
	 * @return array
	 */
	private function _sortTypeFromNFO($nfo = ''): array
	{
		$pattern = '/.+(\.rar|\.001) [0-9a-f]{6,10}?|(imdb)\.[a-z0-9\.\_\-\/]+?(?:tt|\?)\d+?\/?|(tvrage)\.com\/|(\bASIN)|' .
			'(isbn)|(UPC\b)|(comic book)|(comix)|(tv series)|(\bos\b)|(documentaries)|(documentary)|(doku)|(macintosh)|' .
			'(dmg)|(mac[ _\.\-]??os[ _\.\-]??x??)|(\bos\b\s??x??)|(\bosx\b)|(\bios\b)|(iphone)|(ipad)|(ipod)|(pdtv)|' .
			'(hdtv)|(video streams)|(movie)|(audiobook)|(audible)|(recorded books)|(spoken book)|(speech)|(read by)\:?|' .
			'(narrator)\:?|(narrated by)|(dvd)|(ntsc)|(m4v)|(mov\b)|(avi\b)|(xvid)|(divx)|(mkv)|(amazon\.)[a-z]{2,3}.*\/dp\/|' .
			'(anidb.net).*aid=|(\blame\b)|(\btrack)|(trax)|(t r a c k)|(music)|(44.1kHz)|video (game)|type:(game)|(game) Type|' .
			'(game)[ \.]+|(platform)|(console)|\b(win(?:dows|all|xp)\b)|(\bwin\b)|(m3u)|(flac\b)|(?<!writing )(application)(?! util)|' .
			'(plugin)|(\bcrack\b)|(install\b)|(setup)|(magazin)|(x264)|(h264)|(itunes\.apple\.com\/)|(sport)|(deportes)|(nhl)|' .
			'(nfl)|(\bnba)|(ncaa)|(album)|(\bepub\b)|(mobi)|format\W+?[^\r]*(pdf)/iU';

		return preg_split($pattern, $nfo, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
	}
}
