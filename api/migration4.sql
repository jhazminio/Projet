-- Migration SkillSwap #4 : table des messages (messagerie entre utilisateurs)
-- À exécuter dans phpMyAdmin, sur la base "skillswap", onglet SQL,
-- APRÈS avoir déjà exécuté migration.sql, migration2.sql et migration3.sql.

CREATE TABLE MESSAGE (
  idMessage   INT PRIMARY KEY AUTO_INCREMENT,
  idEnvoyeur  INT NOT NULL,
  idReceveur  INT NOT NULL,
  contenu     TEXT NOT NULL,
  dateMessage DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (idEnvoyeur) REFERENCES USER(idUser),
  FOREIGN KEY (idReceveur) REFERENCES USER(idUser)
);
