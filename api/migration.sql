-- Migration SkillSwap : sessions, QCM, jetons, photo de profil
-- À exécuter dans phpMyAdmin, sur la base "skillswap", onglet SQL.

-- Photo de profil (stockée en base64)
ALTER TABLE USER ADD COLUMN photo LONGTEXT NULL;

-- Détails d'une compétence publiée (catégorie, description, coût en jetons/heure)
ALTER TABLE ECHANGE ADD COLUMN categorie VARCHAR(50) NOT NULL DEFAULT 'Autre';
ALTER TABLE ECHANGE ADD COLUMN description TEXT NULL;
ALTER TABLE ECHANGE ADD COLUMN coutHeure INT NOT NULL DEFAULT 1;

-- Réservation d'une session de cours sur une compétence publiée
CREATE TABLE SESSION_ECHANGE (
  idSession          INT PRIMARY KEY AUTO_INCREMENT,
  idEchange          INT NOT NULL,
  idApprenant        INT NOT NULL,
  titre              VARCHAR(150) NOT NULL,
  dateSession        DATE NOT NULL,
  heureSession       TIME NOT NULL,
  format             ENUM('virtuel','presentiel') NOT NULL DEFAULT 'virtuel',
  statut             ENUM('proposee','en_cours','terminee','validee','annulee') NOT NULL DEFAULT 'proposee',
  confirmeEnseignant TINYINT(1) NOT NULL DEFAULT 0,
  confirmeApprenant  TINYINT(1) NOT NULL DEFAULT 0,
  qcmValide          TINYINT(1) NOT NULL DEFAULT 0,
  qcmScore           INT NULL,
  dateCreation       DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (idEchange) REFERENCES ECHANGE(idEchange),
  FOREIGN KEY (idApprenant) REFERENCES USER(idUser)
);

-- Questions du QCM que l'enseignant fait passer à l'apprenant après la session
CREATE TABLE QCM_QUESTION (
  idQuestion   INT PRIMARY KEY AUTO_INCREMENT,
  idSession    INT NOT NULL,
  enonce       VARCHAR(255) NOT NULL,
  optionA      VARCHAR(150) NOT NULL,
  optionB      VARCHAR(150) NOT NULL,
  optionC      VARCHAR(150) NOT NULL,
  optionD      VARCHAR(150) NOT NULL,
  bonneReponse CHAR(1) NOT NULL,
  FOREIGN KEY (idSession) REFERENCES SESSION_ECHANGE(idSession)
);

-- Historique des jetons gagnés / dépensés par utilisateur
CREATE TABLE JETON_HISTORIQUE (
  idJeton     INT PRIMARY KEY AUTO_INCREMENT,
  idUser      INT NOT NULL,
  montant     INT NOT NULL,
  motif       VARCHAR(150) NOT NULL,
  idSession   INT NULL,
  dateJeton   DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (idUser) REFERENCES USER(idUser),
  FOREIGN KEY (idSession) REFERENCES SESSION_ECHANGE(idSession)
);
