ALTER TABLE {$NAMESPACE}_project.project_columnposition
  ADD column dateCreated INT(10) UNSIGNED;

ALTER TABLE {$NAMESPACE}_project.project_columnposition
  ADD column dateModified INT(10) UNSIGNED;

UPDATE {$NAMESPACE}_project.project_columnposition
  SET dateCreated = UNIX_TIMESTAMP();

UPDATE {$NAMESPACE}_project.project_columnposition
  SET dateModified = UNIX_TIMESTAMP();
