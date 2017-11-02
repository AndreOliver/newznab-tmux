# Add new release_processing table

DROP TABLE IF EXISTS release_processing;
CREATE TABLE release_processing (
  releases_id       INT(11) UNSIGNED    NOT NULL DEFAULT '0' COMMENT 'FK to releases.id',
  proc_pp           TINYINT(1)          NOT NULL DEFAULT '0',
  proc_sorter       TINYINT(1)          NOT NULL DEFAULT '0',
  proc_par2         TINYINT(1)          NOT NULL DEFAULT '0',
  proc_nfo          TINYINT(1)          NOT NULL DEFAULT '0',
  proc_files        TINYINT(1)          NOT NULL DEFAULT '0',
  proc_uid          TINYINT(1)          NOT NULL DEFAULT '0',
  proc_srr          TINYINT(1)          NOT NULL DEFAULT '0' COMMENT 'Has the release been srr
processed',
  proc_hash16k      TINYINT(1)          NOT NULL DEFAULT '0' COMMENT 'Has the release been hash16k
processed',
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (releases_id)
)
  ENGINE          = InnoDB
  DEFAULT CHARSET = utf8
  COLLATE         = utf8_unicode_ci
  ROW_FORMAT      = DYNAMIC;