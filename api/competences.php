<?php
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/config.php';

function erreur(int $code, string $message): void
{
    http_response_code($code);
    echo json_encode(['erreur' => $message]);
    exit;
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'liste':
        liste($pdo);
        break;
    case 'mesCompetences':
        mesCompetences($pdo);
        break;
    case 'publier':
        publier($pdo);
        break;
    default:
        erreur(404, 'Action inconnue.');
}

// Toutes les compétences publiées, tous utilisateurs confondus (pour Découvrir)
function liste(PDO $pdo): void
{
    $stmt = $pdo->query(
        'SELECT e.idEchange, e.dateEchange,
                u.idUser, u.nomUser, u.prenomUser, u.universite,
                c.idCompetences, c.nom AS competence
         FROM ECHANGE e
         JOIN USER u ON u.idUser = e.idUser
         JOIN COMPETENCES c ON c.idCompetences = e.idComp
         ORDER BY e.dateEchange DESC'
    );
    echo json_encode($stmt->fetchAll());
}

// Compétences publiées par un utilisateur précis (pour Mon profil)
function mesCompetences(PDO $pdo): void
{
    $idUser = (int) ($_GET['idUser'] ?? 0);
    if ($idUser <= 0) {
        erreur(400, 'idUser requis.');
    }

    $stmt = $pdo->prepare(
        'SELECT e.idEchange, e.dateEchange, c.idCompetences, c.nom AS competence
         FROM ECHANGE e
         JOIN COMPETENCES c ON c.idCompetences = e.idComp
         WHERE e.idUser = ?
         ORDER BY e.dateEchange DESC'
    );
    $stmt->execute([$idUser]);
    echo json_encode($stmt->fetchAll());
}

// Ajoute une compétence au profil de l'utilisateur connecté
function publier(PDO $pdo): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        erreur(405, 'Méthode non autorisée.');
    }

    $data   = json_decode(file_get_contents('php://input'), true) ?? [];
    $idUser = (int) ($data['idUser'] ?? 0);
    $nom    = trim((string) ($data['nom'] ?? ''));

    if ($idUser <= 0) {
        erreur(400, 'Utilisateur invalide.');
    }
    if ($nom === '') {
        erreur(400, 'Le nom de la compétence est requis.');
    }

    $stmt = $pdo->prepare('SELECT idUser FROM USER WHERE idUser = ?');
    $stmt->execute([$idUser]);
    if (!$stmt->fetch()) {
        erreur(404, 'Utilisateur introuvable.');
    }

    $stmtFindComp   = $pdo->prepare('SELECT idCompetences FROM COMPETENCES WHERE nom = ?');
    $stmtCreateComp = $pdo->prepare('INSERT INTO COMPETENCES (nom) VALUES (?)');

    $stmtFindComp->execute([$nom]);
    $comp = $stmtFindComp->fetch();

    if ($comp) {
        $idComp = $comp['idCompetences'];

        $stmtExisting = $pdo->prepare('SELECT idEchange FROM ECHANGE WHERE idUser = ? AND idComp = ?');
        $stmtExisting->execute([$idUser, $idComp]);
        if ($stmtExisting->fetch()) {
            erreur(409, 'Tu proposes déjà cette compétence.');
        }
    } else {
        $stmtCreateComp->execute([$nom]);
        $idComp = $pdo->lastInsertId();
    }

    $stmt = $pdo->prepare('INSERT INTO ECHANGE (idUser, idComp) VALUES (?, ?)');
    $stmt->execute([$idUser, $idComp]);

    http_response_code(201);
    echo json_encode([
        'idEchange'      => (int) $pdo->lastInsertId(),
        'idCompetences'  => (int) $idComp,
        'competence'     => $nom,
    ]);
}
