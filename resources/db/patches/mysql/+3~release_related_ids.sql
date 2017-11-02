# Add release_related_ids table

DROP TABLE IF EXISTS release_related_ids;
CREATE TABLE release_related_ids (
  releases_id       INT(11) UNSIGNED    NOT NULL DEFAULT '0' COMMENT 'FK to releases.id',
  videos_id         MEDIUMINT(11) UNSIGNED         NOT NULL DEFAULT '0' COMMENT 'FK to videos.id of the parent series.',
  tv_episodes_id    MEDIUMINT(11) SIGNED           NOT NULL DEFAULT '0' COMMENT 'FK to tv_episodes.id for the episode.',
  imdbid            MEDIUMINT(7) UNSIGNED ZEROFILL NULL,
  xxxinfo_id        INT SIGNED                     NOT NULL DEFAULT '0' 'FK to xxxinfo.id',
  musicinfo_id      INT(11) SIGNED               NULL COMMENT 'FK to musicinfo.id',
  consoleinfo_id    INT(11) SIGNED               NULL COMMENT 'FK to consoleinfo.id',
  gamesinfo_id      INT SIGNED                     NOT NULL DEFAULT '0' 'FK to gamesinfo.id',
  bookinfo_id       INT(11) SIGNED               NULL COMMENT 'FK to bookinfo.id',
  anidbid           INT                            NULL COMMENT 'FK to anidb_titles.anidbid',
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (releases_id),
  INDEX ix_releases_videos_id                 (videos_id),
  INDEX ix_releases_tv_episodes_id            (tv_episodes_id),
  INDEX ix_releases_imdbid                    (imdbid),
  INDEX ix_releases_xxxinfo_id                (xxxinfo_id),
  INDEX ix_releases_musicinfo_id              (musicinfo_id),
  INDEX ix_releases_consoleinfo_id            (consoleinfo_id),
  INDEX ix_releases_gamesinfo_id              (gamesinfo_id),
  INDEX ix_releases_bookinfo_id               (bookinfo_id),
  INDEX ix_releases_anidbid                   (anidbid)
)
ENGINE          = InnoDB
DEFAULT CHARSET = utf8
COLLATE         = utf8_unicode_ci
ROW_FORMAT      = DYNAMIC;