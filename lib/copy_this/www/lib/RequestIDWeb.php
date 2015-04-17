<?php

require_once (NN_LIB . 'util.php');
require_once (NN_LIB . 'RequestID.php');

/**
 * Attempts to find a PRE name for a release using a request id from our local pre database,
 * or internet request id database.
 *
 * Class RequestIDWeb
 */
class RequestIDWeb extends RequestID
{
	const MAX_WEB_LOOKUPS = 75; // Please don't exceed this, not to be to harsh on the Request id server.

	/**
	 * The id of the PRE entry the found request id belongs to.
	 * @var bool|int
	 */
	protected $_preDbID = false;

	/**
	 * @var int
	 */
	protected $_request_hours;

	/**
	 * Construct.
	 *
	 * @param array $options Class instances / Echo to cli?
	 */
	public function __construct(array $options = array())
	{
		parent::__construct($options);
		$s = new Sites();
		$this->site = $s->get();
		$this->_request_hours = ($this->site->request_hours != '') ? (int)$this->site->request_hours : 1;
	}

	/**
	 * Get all results from the releases table that have request id's to be processed.
	 */
	protected function _getReleases()
	{
		$this->_releases = $this->pdo->queryDirect(
			sprintf ('
				SELECT r.id, r.name, r.searchname, g.name AS groupname, r.groupid, r.categoryid
				FROM releases r
				INNER JOIN groups g ON r.groupid = g.id
				WHERE r.nzbstatus = 1
				AND r.prehashid = 0
				AND r.isrequestid = 1
				AND (
					r.reqidstatus = %d
					OR (r.reqidstatus = %d AND r.adddate < NOW() - INTERVAL %d HOUR)
				)
				%s %s %s
				ORDER BY r.postdate DESC
				LIMIT %d',
				self::REQID_NOLL,
				self::REQID_NONE,
				$this->_request_hours,
				(empty($this->_groupID) ? '' : ('AND r.groupid = ' . $this->_groupID)),
				$this->_getReqIdGroups(),
				($this->_maxTime === 0 ? '' : sprintf(' AND r.adddate > NOW() - INTERVAL %d HOUR', $this->_maxTime)),
				(empty($this->_limit) || $this->_limit > 1000 ? 1000 : $this->_limit)
			)
		);
	}

	/**
	 * Create "AND" part of query for request id groups.
	 * Less load on the request id web server, by limiting results.
	 *
	 * @return string
	 */
	protected function _getReqIdGroups()
	{
		return (
			"AND g.name IN (
				'alt.binaries.boneless',
				'alt.binaries.cd.image',
				'alt.binaries.console.ps3',
				'alt.binaries.erotica',
				'alt.binaries.games.nintendods',
				'alt.binaries.games.wii',
				'alt.binaries.games.xbox360',
				'alt.binaries.inner-sanctum',
				'alt.binaries.mom',
				'alt.binaries.moovee',
				'alt.binaries.movies.divx',
				'alt.binaries.sony.psp',
				'alt.binaries.sounds.mp3.complete_cd',
				'alt.binaries.sounds.flac',
				'alt.binaries.teevee',
				'alt.binaries.warez'," .

				// Extra groups we will need to remap later, etc is teevee for example.
				"'alt.binaries.etc'
			)"
		);
	}

	/**
	 * Process releases for requestid's.
	 *
	 * @return int How many did we rename?
	 */
	protected function _processReleases()
	{
		// Array to store results.
		$requestArray = array();

		if ($this->_releases instanceof \Traversable) {
			// Loop all the results.
			foreach($this->_releases as $release) {

				$this->_release['name'] = $release['name'];
				// Try to find a request id for the release.
				$requestId = $this->_siftReqId();

				// If there's none, update the release and continue.
				if ($requestId === self::REQID_ZERO) {
					$this->_requestIdNotFound($release['id'], self::REQID_NONE);
					if ($this->echoOutput) {
						echo '-';
					}
					continue;
				}

				// Change etc to teevee.
				if ($release['groupname'] === 'alt.binaries.etc') {
					$release['groupname'] = 'alt.binaries.teevee';
				}

				// Send the release id so we can track the return data.
				$requestArray[$release['id']] = array(
					'reqid' => $requestId,
					'ident' => $release['id'],
					'group' => $release['groupname'],
					'sname' => $release['searchname']
				);
			}
		}

		// Check if we requests to send to the web.
		if (count($requestArray) < 1) {
			return 0;
		}

		// Mock array for isset check on server.
		$requestArray[0] = ['ident' => 0, 'group' => 'none', 'reqid' => 0];

		// Do a web lookup.
		$returnXml = Utility::getUrl([
				'url' => $this->site->request_url,
				'method' => 'post',
				'postdata' => 'data=' . serialize($requestArray),
				'verifycert' => false,
			]
		);

		$renamed = 0;
		// Change the release titles and insert the PRE's if they don't exist.
		if ($returnXml !== false) {
			$returnXml = @simplexml_load_string($returnXml);
			if ($returnXml !== false) {

				// Store the returned identifiers so we can check which releases we didn't find a request id.
				$returnedIdentifiers = [];

				$groupIDArray = [];
				foreach($returnXml->request as $result) {
					if (isset($result['name']) && isset($result['ident']) && (int)$result['ident'] > 0) {
						$this->_newTitle['title'] = (string)$result['name'];
						$this->_requestID = (int)$result['reqid'];
						$this->_release['id'] = (int)$result['ident'];

						// Buffer groupid queries.
						$this->_release['groupname'] = $requestArray[(int)$result['ident']]['group'];
						if (isset($groupIDarray[$this->_release['groupname']])) {
							$this->_release['groupid'] = $groupIDArray[$this->_release['groupname']];
						} else {
							$this->_release['groupid'] = $this->groups->getIDByName($this->_release['groupname']);
							$groupIDArray[$this->_release['groupname']] = $this->_release['groupid'];
						}
						$this->_release['gid'] = $this->_release['groupid'];

						$this->_release['searchname'] = $requestArray[(int)$result['ident']]['sname'];
						$this->_insertIntoPreDB();
						if ($this->_preDbID === false) {
							$this->_preDbID = 0;
						}
						$this->_newTitle['id'] = $this->_preDbID;
						$this->_updateRelease();
						$renamed++;
						if ($this->echoOutput) {
							echo '+';
						}
						$returnedIdentifiers[] = (string)$result['ident'];
					}
				}

				// Check if the WEB didn't send back some titles, update the release.
				if (count($returnedIdentifiers) > 0) {
					foreach ($returnedIdentifiers as $identifier) {
						if (array_key_exists($identifier, $requestArray)) {
							unset($requestArray[$identifier]);
						}
					}
				}

				unset($requestArray[0]);
				foreach ($requestArray as $request) {

					$addDate = $this->pdo->queryOneRow(
						sprintf(
							'SELECT UNIX_TIMESTAMP(adddate) AS adddate FROM releases WHERE id = %d', $request['ident']
						)
					);

					$status = self::REQID_NONE;
					if ($addDate !== false && !empty($addDate['adddate'])) {
						if ((bool) (intval((time() - (int)$addDate['adddate']) / 3600) > $this->_request_hours)) {
							$status = self::REQID_OLD;
						}
					} else {
						$status = self::REQID_OLD;
					}

					$this->_requestIdNotFound(
						$request['ident'],
						$status
					);
					if ($this->echoOutput) {
						echo '-';
					}
				}
			}
		}
		return $renamed;
	}

	/**
	 * If we found a request id on the internet, check if our PRE database has it, insert it if not.
	 */
	protected function _insertIntoPreDB()
	{
		$dupeCheck = $this->pdo->queryOneRow(
			sprintf('
				SELECT id AS prehashid, requestid, groupid
				FROM prehash
				WHERE title = %s',
				$this->pdo->escapeString($this->_newTitle['title'])
			)
		);

		if ($dupeCheck === false) {
			$this->_preDbID = (int)$this->pdo->queryInsert(
				sprintf("
					INSERT INTO prehash (title, source, requestid, groupid, predate)
					VALUES (%s, %s, %d, %d, NOW())",
					$this->pdo->escapeString($this->_newTitle['title']),
					$this->pdo->escapeString('requestWEB'),
					$this->_requestID,
					$this->_release['groupid']
				)
			);
		} else {
			$this->_preDbID = $dupeCheck['prehashid'];
			$this->pdo->queryExec(
				sprintf('
					UPDATE prehash
					SET requestid = %d, groupid = %d
					WHERE id = %d',
					$this->_requestID,
					$this->_release['groupid'],
					$this->_preDbID
				)
			);
		}
	}

	/**
	 * If we found a PRE name, update the releases name and reset post processing.
	 */
	protected function _updateRelease()
	{
		$determinedCategory = $this->category->determineCategory($this->_release['groupid'], $this->_newTitle['title']);
		$newTitle = $this->pdo->escapeString($this->_newTitle['title']);
		$this->pdo->queryExec(
			sprintf('
				UPDATE releases
				SET rageid = -1, seriesfull = NULL, season = NULL, episode = NULL, tvtitle = NULL,
				tvairdate = NULL, imdbid = NULL, musicinfoid = NULL, consoleinfoid = NULL, bookinfoid = NULL, anidbid = NULL,
				reqidstatus = %d, isrenamed = 1, proc_files = 1, searchname = %s, categoryid = %d,
				prehashid = %d
				WHERE id = %d',
				self::REQID_FOUND,
				$newTitle,
				$determinedCategory,
				$this->_preDbID,
				$this->_release['id']
			)
		);
		$this->sphinx->updateReleaseSearchName($this->_release['id'], $newTitle);

		if ($this->echoOutput) {
			\NameFixer::echoChangedReleaseName(array(
					'new_name' => $this->_newTitle['title'],
					'old_name' => $this->_release['searchname'],
					'new_category' => $this->category->getNameByID($determinedCategory),
					'old_category' => '',
					'group' => $this->_release['groupname'],
					'release_id' => $this->_release['id'],
					'method' => 'RequestID->updateRelease<web>'
				)
			);
		}
	}
}