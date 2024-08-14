ALTER TABLE {$NAMESPACE}_maniphest.edge
  ADD column dateModified INT(10) UNSIGNED;

CREATE TRIGGER before_phabricator_maniphest_edge_insert
  BEFORE INSERT ON phabricator_maniphest.edge
    FOR EACH ROW SET NEW.dateCreated = UNIX_TIMESTAMP(), NEW.dateModified = UNIX_TIMESTAMP();

CREATE TRIGGER before_phabricator_maniphest_edge_update
  BEFORE UPDATE ON phabricator_maniphest.edge
    FOR EACH ROW SET NEW.dateModified = UNIX_TIMESTAMP();

UPDATE {$NAMESPACE}_maniphest.edge
  SET dateModified = UNIX_TIMESTAMP();
