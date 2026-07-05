-- SkillSwap : schéma complet de la base de données "skillswap"
-- Regroupe en un seul fichier toutes les tables et colonnes créées au fil du projet
-- (remplace les anciens migration.sql à migration7.sql, qui les ajoutaient une par une).
--
-- À utiliser sur une base neuve : exécuter tel quel dans phpMyAdmin, onglet SQL.
-- Si ta base "skillswap" existe déjà avec ces tables, inutile de le rejouer.

CREATE TABLE IF NOT EXISTS USER (
  idUser      INT PRIMARY KEY AUTO_INCREMENT,
  nomUser     VARCHAR(100) NOT NULL,
  prenomUser  VARCHAR(100) NOT NULL,
  email       VARCHAR(150) NOT NULL UNIQUE,
  universite  VARCHAR(150) NULL,
  motDePasse  VARCHAR(255) NOT NULL,
  photo       LONGTEXT NULL
);

CREATE TABLE IF NOT EXISTS COMPETENCES (
  idCompetences INT PRIMARY KEY AUTO_INCREMENT,
  nom           VARCHAR(100) NOT NULL UNIQUE
);

-- Compétence publiée par un utilisateur (ce qu'il propose d'enseigner)
CREATE TABLE IF NOT EXISTS ECHANGE (
  idEchange     INT PRIMARY KEY AUTO_INCREMENT,
  dateEchange   DATETIME DEFAULT CURRENT_TIMESTAMP,
  idUser        INT NOT NULL,
  idComp        INT NOT NULL,
  categorie     VARCHAR(50) NOT NULL DEFAULT 'Autre',
  description   TEXT NULL,
  coutHeure     INT NOT NULL DEFAULT 1,
  format        VARCHAR(20) NOT NULL DEFAULT 'virtuel',
  support       VARCHAR(150) NULL,
  imageEchange  LONGTEXT NULL,
  FOREIGN KEY (idUser) REFERENCES USER(idUser),
  FOREIGN KEY (idComp) REFERENCES COMPETENCES(idCompetences)
);

-- Réservation d'une session de cours sur une compétence publiée
CREATE TABLE IF NOT EXISTS SESSION_ECHANGE (
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
  nbHeures           INT NOT NULL DEFAULT 1,
  heuresReelles      INT NULL,
  idSessionMiroir    INT NULL,
  dateCreation       DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (idEchange) REFERENCES ECHANGE(idEchange),
  FOREIGN KEY (idApprenant) REFERENCES USER(idUser),
  CONSTRAINT fk_session_miroir FOREIGN KEY (idSessionMiroir) REFERENCES SESSION_ECHANGE(idSession)
);

-- Questions du QCM que l'enseignant fait passer à l'apprenant après la session
CREATE TABLE IF NOT EXISTS QCM_QUESTION (
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
CREATE TABLE IF NOT EXISTS JETON_HISTORIQUE (
  idJeton     INT PRIMARY KEY AUTO_INCREMENT,
  idUser      INT NOT NULL,
  montant     INT NOT NULL,
  motif       VARCHAR(150) NOT NULL,
  idSession   INT NULL,
  dateJeton   DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (idUser) REFERENCES USER(idUser),
  FOREIGN KEY (idSession) REFERENCES SESSION_ECHANGE(idSession)
);

-- Avis / évaluations laissés entre utilisateurs après une session validée
CREATE TABLE IF NOT EXISTS AVIS (
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

-- Messagerie entre utilisateurs
CREATE TABLE IF NOT EXISTS MESSAGE (
  idMessage   INT PRIMARY KEY AUTO_INCREMENT,
  idEnvoyeur  INT NOT NULL,
  idReceveur  INT NOT NULL,
  contenu     TEXT NOT NULL,
  dateMessage DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (idEnvoyeur) REFERENCES USER(idUser),
  FOREIGN KEY (idReceveur) REFERENCES USER(idUser)
);
