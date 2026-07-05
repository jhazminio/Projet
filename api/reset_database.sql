-- SkillSwap : remise à zéro complète de la base de données
-- ATTENTION : IRRÉVERSIBLE. Supprime tous les utilisateurs, compétences publiées,
-- sessions/propositions, messages, avis, questions de QCM et l'historique de jetons.
-- À exécuter dans phpMyAdmin, sur la base "skillswap", onglet SQL.

SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE AVIS;
TRUNCATE TABLE JETON_HISTORIQUE;
TRUNCATE TABLE QCM_QUESTION;
TRUNCATE TABLE SESSION_ECHANGE;
TRUNCATE TABLE MESSAGE;
TRUNCATE TABLE ECHANGE;
TRUNCATE TABLE COMPETENCES;
TRUNCATE TABLE USER;

SET FOREIGN_KEY_CHECKS = 1;
