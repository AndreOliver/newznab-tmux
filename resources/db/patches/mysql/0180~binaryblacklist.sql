INSERT IGNORE INTO binaryblacklist (id, groupname, regex, msgcol, optype, status, description)
VALUES (13, '^alt\\.binaries\\.(kenpsx|frogs)$', '^\\s*([a-f0-9]{16})\\s\\[\\d+\\/\\d+\\]\\s-\\s"\\1"\\s+yEnc$', 1, 1, 0, 'Block 16 character hash floods in kenpsx, frogs.');
