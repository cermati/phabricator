ALTER TABLE {$NAMESPACE}_maniphest.maniphest_customfieldstorage
  ADD column dateCreated INT(10) UNSIGNED;

ALTER TABLE {$NAMESPACE}_maniphest.maniphest_customfieldstorage
  ADD column dateModified INT(10) UNSIGNED;

CREATE TRIGGER before_phabricator_maniphest_maniphest_customfieldstorage_insert
  BEFORE INSERT ON phabricator_maniphest.maniphest_customfieldstorage
    FOR EACH ROW SET NEW.dateCreated = UNIX_TIMESTAMP(), NEW.dateModified = UNIX_TIMESTAMP();

CREATE TRIGGER before_phabricator_maniphest_maniphest_customfieldstorage_update
  BEFORE UPDATE ON phabricator_maniphest.maniphest_customfieldstorage
    FOR EACH ROW SET NEW.dateModified = UNIX_TIMESTAMP();

UPDATE {$NAMESPACE}_maniphest.maniphest_customfieldstorage
  SET dateCreated = UNIX_TIMESTAMP();

UPDATE {$NAMESPACE}_maniphest.maniphest_customfieldstorage
  SET dateModified = UNIX_TIMESTAMP();
