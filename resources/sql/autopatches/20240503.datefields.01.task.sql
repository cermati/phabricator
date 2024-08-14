ALTER TABLE {$NAMESPACE}_maniphest.maniphest_task_ffield
  ADD column dateCreated INT(10) UNSIGNED;

ALTER TABLE {$NAMESPACE}_maniphest.maniphest_task_ffield
  ADD column dateModified INT(10) UNSIGNED;

CREATE TRIGGER before_phabricator_maniphest_maniphest_task_ffield_insert
  BEFORE INSERT ON {$NAMESPACE}_maniphest.maniphest_task_ffield
    FOR EACH ROW SET NEW.dateCreated = UNIX_TIMESTAMP(), NEW.dateModified = UNIX_TIMESTAMP();

CREATE TRIGGER before_phabricator_maniphest_maniphest_task_ffield_update
  BEFORE UPDATE ON {$NAMESPACE}_maniphest.maniphest_task_ffield
    FOR EACH ROW SET NEW.dateModified = UNIX_TIMESTAMP();

UPDATE {$NAMESPACE}_maniphest.maniphest_task_ffield
  SET dateCreated = UNIX_TIMESTAMP();

UPDATE {$NAMESPACE}_maniphest.maniphest_task_ffield
  SET dateModified = UNIX_TIMESTAMP();
