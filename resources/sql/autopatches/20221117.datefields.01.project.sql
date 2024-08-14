ALTER TABLE {$NAMESPACE}_project.project_columnposition
  ADD column dateCreated INT(10) UNSIGNED;

ALTER TABLE {$NAMESPACE}_project.project_columnposition
  ADD column dateModified INT(10) UNSIGNED;

CREATE TRIGGER before_phabricator_project_project_columnposition_insert
  BEFORE INSERT ON {$NAMESPACE}_project.project_columnposition
    FOR EACH ROW SET NEW.dateCreated = UNIX_TIMESTAMP(), NEW.dateModified = UNIX_TIMESTAMP();

CREATE TRIGGER before_phabricator_project_project_columnposition_update
  BEFORE UPDATE ON {$NAMESPACE}_project.project_columnposition
    FOR EACH ROW SET NEW.dateModified = UNIX_TIMESTAMP();

UPDATE {$NAMESPACE}_project.project_columnposition
  SET dateCreated = UNIX_TIMESTAMP();

UPDATE {$NAMESPACE}_project.project_columnposition
  SET dateModified = UNIX_TIMESTAMP();
