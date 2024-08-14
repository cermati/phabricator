ALTER TABLE {$NAMESPACE}_maniphest.maniphest_task_ffield
  ADD column dateCreated INT(10) UNSIGNED;

ALTER TABLE {$NAMESPACE}_maniphest.maniphest_task_ffield
  ADD column dateModified INT(10) UNSIGNED;

UPDATE {$NAMESPACE}_maniphest.maniphest_task_ffield
  SET dateCreated = UNIX_TIMESTAMP();

UPDATE {$NAMESPACE}_maniphest.maniphest_task_ffield
  SET dateModified = UNIX_TIMESTAMP();
