ALTER TABLE {$NAMESPACE}_differential.edge
  ADD column dateModified INT(10) UNSIGNED;

CREATE TRIGGER before_phabricator_differential_edge_insert
  BEFORE INSERT ON {$NAMESPACE}_differential.edge
    FOR EACH ROW SET NEW.dateCreated = UNIX_TIMESTAMP(), NEW.dateModified = UNIX_TIMESTAMP();

CREATE TRIGGER before_phabricator_differential_edge_update
  BEFORE UPDATE ON {$NAMESPACE}_differential.edge
    FOR EACH ROW SET NEW.dateModified = UNIX_TIMESTAMP();

UPDATE {$NAMESPACE}_differential.edge
  SET dateModified = UNIX_TIMESTAMP();
