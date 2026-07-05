-- SkillSwap : remise à zéro complète de la base de données
-- ATTENTION : IRRÉVERSIBLE. Supprime tous les utilisateurs, compétences publiées,
-- sessions/propositions, messages, avis, questions de QCM et l'historique de jetons.
-- À exécuter dans phpMyAdmin, sur la base "skillswap", onglet SQL,
-- en collant TOUT le script d'un coup et en cliquant une seule fois sur "Exécuter".
--
-- On supprime dans l'ordre enfant → parent (au lieu de compter sur
-- FOREIGN_KEY_CHECKS=0, qui ne semble pas persister entre les requêtes sur
-- certains hébergeurs). SESSION_ECHANGE se référence elle-même (idSessionMiroir,
-- pour lier les deux moitiés d'un troc) : on casse d'abord ce lien avant de supprimer.

UPDATE SESSION_ECHANGE SET idSessionMiroir = NULL;

DELETE FROM AVIS;
DELETE FROM JETON_HISTORIQUE;
DELETE FROM QCM_QUESTION;
DELETE FROM SESSION_ECHANGE;
DELETE FROM MESSAGE;
DELETE FROM ECHANGE;
DELETE FROM COMPETENCES;
DELETE FROM USER;

ALTER TABLE AVIS AUTO_INCREMENT = 1;
ALTER TABLE JETON_HISTORIQUE AUTO_INCREMENT = 1;
ALTER TABLE QCM_QUESTION AUTO_INCREMENT = 1;
ALTER TABLE SESSION_ECHANGE AUTO_INCREMENT = 1;
ALTER TABLE MESSAGE AUTO_INCREMENT = 1;
ALTER TABLE ECHANGE AUTO_INCREMENT = 1;
ALTER TABLE COMPETENCES AUTO_INCREMENT = 1;
ALTER TABLE USER AUTO_INCREMENT = 1;
