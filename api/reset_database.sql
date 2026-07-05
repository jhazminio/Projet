-- SkillSwap : remise à zéro complète de la base de données
-- ATTENTION : IRRÉVERSIBLE. Supprime tous les utilisateurs, compétences publiées,
-- sessions/propositions, messages, avis, questions de QCM et l'historique de jetons.
-- À exécuter dans phpMyAdmin, sur la base "skillswap", onglet SQL,
-- en collant TOUT le script d'un coup et en cliquant une seule fois sur "Exécuter".
--
-- (On utilise DELETE plutôt que TRUNCATE : TRUNCATE refuse de vider une table tant
-- qu'une autre table la référence par clé étrangère, même avec FOREIGN_KEY_CHECKS=0.)

SET FOREIGN_KEY_CHECKS = 0;

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

SET FOREIGN_KEY_CHECKS = 1;
