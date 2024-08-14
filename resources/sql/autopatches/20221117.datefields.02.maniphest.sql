ALTER TABLE {$NAMESPACE}_maniphest.edge
  ADD column dateModified INT(10) UNSIGNED;

UPDATE {$NAMESPACE}_maniphest.edge
  SET dateModified = UNIX_TIMESTAMP();
