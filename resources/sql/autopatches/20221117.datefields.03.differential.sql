ALTER TABLE {$NAMESPACE}_differential.edge
  ADD column dateModified INT(10) UNSIGNED;

UPDATE {$NAMESPACE}_differential.edge
  SET dateModified = UNIX_TIMESTAMP();
