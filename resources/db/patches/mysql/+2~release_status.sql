# Add new release_status table

DROP TABLE IF EXISTS release_status;
CREATE TABLE release_status (
  releases_id       INT(11) UNSIGNED    NOT NULL DEFAULT '0' COMMENT 'FK to releases.id',
  nfostatus         TINYINT             NOT NULL DEFAULT '0' COMMENT 'Does the release have associated nfo',
  jpgstatus         TINYINT(1)          NOT NULL DEFAULT '0',
  videostatus       TINYINT(1)          NOT NULL DEFAULT '0',
  audiostatus       TINYINT(1)          NOT NULL DEFAULT '0',
  dehashstatus      TINYINT(1)          NOT NULL DEFAULT '0',
  reqidstatus       TINYINT(1)          NOT NULL DEFAULT '0',
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,

  PRIMARY KEY (releases_id),
  INDEX ix_releases_nfostatus                 (nfostatus),
  INDEX ix_releases_dehashstatus              (dehashstatus),
  INDEX ix_releases_reqidstatus               (reqidstatus)
)
  ENGINE          = InnoDB
  DEFAULT CHARSET = utf8
  COLLATE         = utf8_unicode_ci
  ROW_FORMAT      = DYNAMIC;