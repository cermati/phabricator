ALTER TABLE {$NAMESPACE}_maniphest.maniphest_customfieldstorage
  ADD column dateCreated INT(10) UNSIGNED;

ALTER TABLE {$NAMESPACE}_maniphest.maniphest_customfieldstorage
  ADD column dateModified INT(10) UNSIGNED;

UPDATE {$NAMESPACE}_maniphest.maniphest_customfieldstorage
  SET dateCreated = UNIX_TIMESTAMP();

UPDATE {$NAMESPACE}_maniphest.maniphest_customfieldstorage
  SET dateModified = UNIX_TIMESTAMP();
