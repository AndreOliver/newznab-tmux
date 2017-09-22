<?php

namespace nntmux;

use nntmux\db\DB;
use Carbon\Carbon;
use App\Models\Group;

class Groups
{
    /**
     * @var \nntmux\db\DB
     */
    public $pdo;

    /**
     * @var \nntmux\ColorCLI
     */
    public $colorCLI;

    /**
     * The table names for TPG children.
     *
     * @var array
     */
    protected $cbpm;

    /**
     * @var array
     */
    protected $cbppTableNames;

    /**
     * Construct.
     *
     * @param array $options Class instances.
     * @throws \Exception
     */
    public function __construct(array $options = [])
    {
        $defaults = [
            'Settings' => null,
            'ColorCLI' => null,
        ];
        $options += $defaults;

        $this->pdo = ($options['Settings'] instanceof DB ? $options['Settings'] : new DB());
        $this->colorCLI = ($options['ColorCLI'] instanceof ColorCLI ? $options['ColorCLI'] : new ColorCLI());
        $this->cbpm = ['collections', 'binaries', 'parts', 'missed_parts'];
    }

    /**
     * Returns an associative array of groups for list selection.
     *
     * @return array
     */
    public function getGroupsForSelect(): array
    {
        $groups = $this->getActive();
        $temp_array = [];

        $temp_array[-1] = '--Please Select--';

        if (is_array($groups)) {
            foreach ($groups as $group) {
                $temp_array[$group['name']] = $group['name'];
            }
        }

        return $temp_array;
    }

    /**
     * Get all properties of a single group by its ID.
     *
     *
     * @param $id
     * @return \Illuminate\Database\Eloquent\Model|null|static
     */
    public function getByID($id)
    {
        return Group::query()->where('id', $id)->first();
    }

    /**
     * Get all properties of all groups ordered by name ascending.
     *
     * @return array
     */
    public function getActive(): array
    {
        return Group::query()->where('active', '=', 1)->orderBy('name')->get();
    }

    /**
     * Get active backfill groups ordered by name ascending.
     *
     *
     * @param $order
     * @return array|\Illuminate\Database\Eloquent\Collection|static[]
     */
    public function getActiveBackfill($order)
    {
        switch ($order) {
            case '':
            case 'normal':
                return Group::query()->where('backfill', '=', 1)->where('last_record', '!=', 0)->orderBy('name')->get();
                break;
            case 'date':
                return Group::query()->where('backfill', '=', 1)->where('last_record', '!=', 0)->orderBy('first_record_postdate', 'DESC')->get();
                break;
            default:
                return [];
        }
    }

    /**
     * Get all active group IDs.
     *
     *
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function getActiveIDs()
    {
        return Group::query()->where('active', '=', 1)->orderBy('name')->get(['id']);
    }

    /**
     * Get all group columns by Name.
     *
     *
     * @param $grp
     * @return \Illuminate\Database\Eloquent\Model|null|static
     */
    public function getByName($grp)
    {
        return Group::query()->where('name', $grp)->first();
    }

    /**
     * Get a group name using its ID.
     *
     * @param int|string $id The group ID.
     *
     * @return string Empty string on failure, groupName on success.
     */
    public function getNameByID($id): string
    {
        $res = Group::query()->where('id', $id)->first(['name']);

        return $res->name ?? '';
    }

    /**
     * Get a group ID using its name.
     *
     * @param string $name The group name.
     *
     * @return string|int Empty string on failure, groups_id on success.
     */
    public function getIDByName($name)
    {
        $res = Group::query()->where('name', $name)->first(['id']);

        return $res === false ? '' : $res->id;
    }

    /**
     * Gets a count of all groups in the table limited by parameters.
     *
     * @param string $groupname Constrain query to specific group name
     * @param int    $active    Constrain query to active status
     *
     * @return mixed
     */
    public function getCount($groupname = '', $active = -1)
    {
        $res = $this->pdo->query(
            sprintf(
                '
				SELECT COUNT(g.id) AS num
				FROM groups g
				WHERE 1=1 %s %s',
                (
                    $groupname !== ''
                    ?
                    sprintf(
                        'AND g.name %s',
                        $this->pdo->likeString($groupname, true, true)
                    )
                    : ''
                ),
                ($active > -1 ? sprintf('AND g.active = %d', $active) : '')
            ),
            true,
            NN_CACHE_EXPIRY_MEDIUM
        );

        return empty($res) ? 0 : $res[0]['num'];
    }

    /**
     * Gets all groups and associated release counts.
     *
     * @param bool|int $start     The offset of the query or false for no offset
     * @param int      $num       The limit of the query
     * @param string   $groupname The groupname we want if any
     * @param int      $active    The status of the group we want if any
     *
     * @return mixed
     */
    public function getRange($start = false, $num = -1, $groupname = '', $active = -1)
    {
        return $this->pdo->query(
            sprintf(
                '
				SELECT g.*,
				COALESCE(COUNT(r.id), 0) AS num_releases
				FROM groups g
				LEFT OUTER JOIN releases r ON r.groups_id = g.id
				WHERE 1=1 %s %s
				GROUP BY g.id
				ORDER BY g.name ASC
				%s',
                (
                    $groupname !== ''
                    ?
                    sprintf(
                        'AND g.name %s',
                        $this->pdo->likeString($groupname, true, true)
                    )
                    : ''
                ),
                $active > -1 ? sprintf('AND g.active = %d', $active) : '',
                $start === false ? '' : ' LIMIT '.$num.' OFFSET '.$start
            ),
            true,
            NN_CACHE_EXPIRY_SHORT
        );
    }

    /**
     * Update an existing group.
     *
     * @param array $group
     *
     * @return bool
     */
    public function update($group): bool
    {
        return Group::query()->where('id', $group['id'])->update(
            [
                'name' => trim($group['name']),
                'description' => trim($group['description']),
                'backfill_target' => $group['backfill_target'],
                'first_record' => $group['first_record'],
                'last_record' => $group['last_record'],
                'last_updated' => Carbon::now(),
                'active' => $group['active'],
                'backfill' => $group['backfill'],
                'minsizetoformrelease' => $group['minsizetoformrelease'] === '' ? null : $group['minsizetoformrelease'],
                'minfilestoformrelease' => $group['minfilestoformrelease'] === '' ? null : $group['minfilestoformrelease'],
            ]
        );
    }

    /**
     * Checks group name is standard and replaces any shorthand prefixes.
     *
     * @param string $groupName The full name of the usenet group being evaluated
     *
     * @return string|bool The name of the group replacing shorthand prefix or false if groupname was malformed
     */
    public function isValidGroup($groupName)
    {
        if (preg_match('/^([\w-]+\.)+[\w-]+$/i', $groupName)) {
            return preg_replace('/^a\.b\./i', 'alt.binaries.', $groupName, 1);
        }

        return false;
    }

    /**
     * Add a new group.
     *
     * @param array $group
     *
     * @return bool
     */
    public function add($group): bool
    {
        return Group::query()->insertGetId(
            [
                'name' => trim($group['name']),
                'description' => isset($group['description']) ? trim($group['description']) : '',
                'backfill_target' => $group['backfill_target'] ?? 1,
                'first_record' => $group['first_record'] ?? 0,
                'last_record' => $group['last_record'] ?? 0,
                'active' => $group['active'] ?? 0,
                'backfill' => $group['backfill'] ?? 0,
                'minsizetoformrelease' => $group['minsizetoformrelease'] === '' ? null : $group['minsizetoformrelease'],
                'minfilestoformrelease' => $group['minfilestoformrelease'] === '' ? null : $group['minfilestoformrelease'],
            ]
        );
    }

    /**
     * Format numeric string when adding/updating groups.
     *
     * @param string $setting
     * @param bool   $escape
     *
     * @return string|int
     */
    protected function formatNumberString($setting, $escape = true)
    {
        $setting = trim($setting);
        if ($setting === 0 || ! is_numeric($setting)) {
            $setting = 0;
        }

        return $escape ? $this->pdo->escapeString($setting) : (int) $setting;
    }

    /**
     * Delete a group.
     *
     * @param int|string $id ID of the group.
     *
     * @return bool
     * @throws \Exception
     */
    public function delete($id): bool
    {
        $this->purge($id);

        return Group::query()->where('id', $id)->delete();
    }

    /**
     * Reset a group.
     *
     * @param string|int $id The group ID.
     *
     * @return bool
     * @throws \Exception
     */
    public function reset($id): bool
    {
        // Remove rows from collections / binaries / parts.
        (new Binaries(['Groups' => $this, 'Settings' => $this->pdo]))->purgeGroup($id);

        // Remove rows from part repair.
        $this->pdo->queryExec(
            "
			DELETE mp
			FROM missed_parts mp
			WHERE mp.groups_id = {$id}"
        );

        foreach ($this->cbpm as $tablePrefix) {
            $this->pdo->queryExec(
                "DROP TABLE IF EXISTS {$tablePrefix}_{$id}"
            );
        }

        // Reset the group stats.
        return Group::query()->where('id', $id)->update(
            [
                'backfill_target' => 1,
                'first_record' => 0,
                'first_record_postdate' => null,
                'last_record' => 0,
                'člast_record_postdate' => null,
                'last_updated' => null,
                'active' => 0,
            ]
        );
    }

    /**
     * Reset all groups.
     *
     * @return bool
     */
    public function resetall(): bool
    {
        foreach ($this->cbpm as $tablePrefix) {
            $this->pdo->queryExec("TRUNCATE TABLE {$tablePrefix}");
        }

        $groups = $this->pdo->queryDirect('SELECT id FROM groups');

        if ($groups instanceof \Traversable) {
            foreach ($groups as $group) {
                foreach ($this->cbpm as $tablePrefix) {
                    $this->pdo->queryExec("DROP TABLE IF EXISTS {$tablePrefix}_{$group['id']}");
                }
            }
        }

        // Reset the group stats.

        return Group::query()->update(
            [
            'backfill_target' => 1,
            'first_record' => 0,
            'first_record_postdate' => null,
            'last_record' => 0,
            'člast_record_postdate' => null,
            'last_updated' => null,
            'active' => 0,
        ]
        );
    }

    /**
     * Purge a single group or all groups.
     *
     * @param int|string|bool $id The group ID. If false, purge all groups.
     * @throws \Exception
     */
    public function purge($id = false)
    {
        if ($id === false) {
            $this->resetall();
        } else {
            $this->reset($id);
        }

        $res = $this->pdo->queryDirect(
            sprintf(
                '
				SELECT r.id, r.guid
				FROM releases r %s',
                ($id === false ? '' : 'WHERE r.groups_id = '.$id)
            )
        );

        if ($res instanceof \Traversable) {
            $releases = new Releases(['Settings' => $this->pdo, 'Groups' => $this]);
            $nzb = new NZB($this->pdo);
            $releaseImage = new ReleaseImage($this->pdo);
            foreach ($res as $row) {
                $releases->deleteSingle(
                    [
                        'g' => $row['guid'],
                        'i' => $row['id'],
                    ],
                    $nzb,
                    $releaseImage
                );
            }
        }
    }

    /**
     * Adds new newsgroups based on a regular expression match against USP available.
     *
     * @param string $groupList
     * @param int $active
     * @param int $backfill
     *
     * @return array|string
     * @throws \Exception
     */
    public function addBulk($groupList, $active = 1, $backfill = 1)
    {
        if (preg_match('/^\s*$/m', $groupList)) {
            $ret = 'No group list provided.';
        } else {
            $nntp = new NNTP(['Echo' => false]);
            if ($nntp->doConnect() !== true) {
                return 'Problem connecting to usenet.';
            }
            $groups = $nntp->getGroups();
            $nntp->doQuit();

            if ($nntp->isError($groups)) {
                return 'Problem fetching groups from usenet.';
            }

            $regFilter = '/'.$groupList.'/i';

            $ret = [];

            foreach ($groups as $group) {
                if (preg_match($regFilter, $group['group']) > 0) {
                    $res = $this->getIDByName($group['group']);
                    if ($res === '') {
                        $this->add(
                            [
                                'name'        => $group['group'],
                                'active'      => $active,
                                'backfill'    => $backfill,
                                'description' => 'Added by bulkAdd',
                            ]
                        );
                        $ret[] = ['group' => $group['group'], 'msg' => 'Created'];
                    }
                }
            }

            if (count($ret) === 0) {
                $ret = 'No groups found with your regex, try again!';
            }
        }

        return $ret;
    }

    /**
     * Updates the group active/backfill status.
     *
     * @param int    $id     Which group ID
     * @param string $column Which column active/backfill
     * @param int    $status Which status we are setting
     *
     * @return string
     */
    public function updateGroupStatus($id, $column, $status = 0): string
    {
        $this->pdo->queryExec(
            "
			UPDATE groups
			SET {$column} = {$status}
			WHERE id = {$id}"
        );

        return "Group {$id}: {$column} has been ".(($status === 0) ? 'deactivated' : 'activated').'.';
    }

    /**
     * Get the names of the collections/binaries/parts/part repair tables.
     * If TPG is on, try to create new tables for the groups_id, if we fail, log the error and exit.
     *
     * @param int $groupID ID of the group.
     *
     * @return array The table names.
     */
    public function getCBPTableNames($groupID): array
    {
        $groupKey = $groupID;

        // Check if buffered and return. Prevents re-querying MySQL when TPG is on.
        if (isset($this->cbppTableNames[$groupKey])) {
            return $this->cbppTableNames[$groupKey];
        }

        if (NN_ECHOCLI && $this->createNewTPGTables($groupID) === false) {
            exit('There is a problem creating new TPG tables for this group ID: '.$groupID.PHP_EOL);
        }

        $tables = [];
        $tables['cname'] = 'collections_'.$groupID;
        $tables['bname'] = 'binaries_'.$groupID;
        $tables['pname'] = 'parts_'.$groupID;
        $tables['prname'] = 'missed_parts_'.$groupID;

        // Buffer.
        $this->cbppTableNames[$groupKey] = $tables;

        return $tables;
    }

    /**
     * Check if the tables exist for the groups_id, make new tables for table per group.
     *
     * @param int $groupID
     *
     * @return bool
     */
    public function createNewTPGTables($groupID): bool
    {
        foreach ($this->cbpm as $tablePrefix) {
            if ($this->pdo->queryExec(
                    "CREATE TABLE IF NOT EXISTS {$tablePrefix}_{$groupID} LIKE {$tablePrefix}",
                    true
                ) === false
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Disable group that does not exist on USP server.
     *
     * @param int $id The Group ID to disable
     */
    public function disableIfNotExist($id): void
    {
        $this->updateGroupStatus($id, 'active', 0);
        ColorCLI::doEcho(
            ColorCLI::error(
                'Group does not exist on server, disabling'
            )
        );
    }
}
