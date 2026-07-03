-- Migration SkillSwap #2 : avis / évaluations entre utilisateurs
-- À exécuter dans phpMyAdmin, sur la base "skillswap", onglet SQL,
-- APRÈS avoir déjà exécuté api/migration.sql.

CREATE TABLE AVIS (
  idAvis      INT PRIMARY KEY AUTO_INCREMENT,
  idSession   INT NOT NULL,
  idAuteur    INT NOT NULL,
  idCible     INT NOT NULL,
  note        TINYINT NOT NULL,
  commentaire TEXT NULL,
  dateAvis    DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (idSession) REFERENCES SESSION_ECHANGE(idSession),
  FOREIGN KEY (idAuteur) REFERENCES USER(idUser),
  FOREIGN KEY (idCible) REFERENCES USER(idUser),
  UNIQUE KEY unique_avis_session_auteur (idSession, idAuteur)
);
