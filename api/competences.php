<?php
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/config.php';

const CATEGORIES = [
    'Musique', 'Programmation', 'Sport', 'Mathématiques', 'Langues', 'Danse',
    'Informatique', 'Sciences', 'Arts', 'Théâtre', 'Photographie', 'Dessin',
];

function erreur(int $code, string $message): void
{
    http_response_code($code);
    echo json_encode(['erreur' => $message]);
    exit;
}

function categorieValide(string $cat): string
{
    return in_array($cat, CATEGORIES, true) ? $cat : 'Autre';
}

const FORMATS = ['virtuel', 'presentiel', 'les_deux'];

function formatValide(string $f): string
{
    return in_array($f, FORMATS, true) ? $f : 'virtuel';
}

const NOTE_SUBQUERY = '
    (SELECT AVG(a.note) FROM AVIS a
       JOIN SESSION_ECHANGE s ON s.idSession = a.idSession
       WHERE s.idEchange = e.idEchange AND a.idCible = e.idUser) AS noteMoyenne,
    (SELECT COUNT(*) FROM AVIS a
       JOIN SESSION_ECHANGE s ON s.idSession = a.idSession
       WHERE s.idEchange = e.idEchange AND a.idCible = e.idUser) AS nbAvis,
    (SELECT COUNT(*) FROM SESSION_ECHANGE s
       WHERE s.idEchange = e.idEchange AND s.statut = \'validee\') AS nbEchanges
';

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'liste':
        liste($pdo);
        break;
    case 'detail':
        detail($pdo);
        break;
    case 'mesCompetences':
        mesCompetences($pdo);
        break;
    case 'publier':
        publier($pdo);
        break;
    case 'modifier':
        modifier($pdo);
        break;
    case 'supprimer':
        supprimer($pdo);
        break;
    default:
        erreur(404, 'Action inconnue.');
}

// Toutes les compétences publiées, tous utilisateurs confondus (pour Découvrir)
function liste(PDO $pdo): void
{
    $stmt = $pdo->query(
        'SELECT e.idEchange, e.dateEchange, e.categorie, e.description, e.coutHeure, e.format, e.support,
                u.idUser, u.nomUser, u.prenomUser, u.universite, u.photo,
                c.idCompetences, c.nom AS competence,
                ' . NOTE_SUBQUERY . '
         FROM ECHANGE e
         JOIN USER u ON u.idUser = e.idUser
         JOIN COMPETENCES c ON c.idCompetences = e.idComp
         ORDER BY e.dateEchange DESC'
    );
    echo json_encode($stmt->fetchAll());
}

// Détail d'une compétence publiée précise (utilisé pour définir une session)
function detail(PDO $pdo): void
{
    $idEchange = (int) ($_GET['idEchange'] ?? 0);
    if ($idEchange <= 0) {
        erreur(400, 'idEchange requis.');
    }

    $stmt = $pdo->prepare(
        'SELECT e.idEchange, e.categorie, e.description, e.coutHeure, e.format, e.support,
                u.idUser, u.nomUser, u.prenomUser, u.universite, u.photo,
                c.idCompetences, c.nom AS competence,
                ' . NOTE_SUBQUERY . '
         FROM ECHANGE e
         JOIN USER u ON u.idUser = e.idUser
         JOIN COMPETENCES c ON c.idCompetences = e.idComp
         WHERE e.idEchange = ?'
    );
    $stmt->execute([$idEchange]);
    $row = $stmt->fetch();

    if (!$row) {
        erreur(404, 'Compétence introuvable.');
    }

    echo json_encode($row);
}

// Compétences publiées par un utilisateur précis (pour Mon profil / profil public)
function mesCompetences(PDO $pdo): void
{
    $idUser = (int) ($_GET['idUser'] ?? 0);
    if ($idUser <= 0) {
        erreur(400, 'idUser requis.');
    }

    $stmt = $pdo->prepare(
        'SELECT e.idEchange, e.dateEchange, e.categorie, e.description, e.coutHeure, e.format, e.support,
                u.idUser, u.nomUser, u.prenomUser, u.universite, u.photo,
                c.idCompetences, c.nom AS competence
         FROM ECHANGE e
         JOIN USER u ON u.idUser = e.idUser
         JOIN COMPETENCES c ON c.idCompetences = e.idComp
         WHERE e.idUser = ?
         ORDER BY e.dateEchange DESC'
    );
    $stmt->execute([$idUser]);
    echo json_encode($stmt->fetchAll());
}

function trouverOuCreerCompetence(PDO $pdo, string $nom): int
{
    $stmt = $pdo->prepare('SELECT idCompetences FROM COMPETENCES WHERE nom = ?');
    $stmt->execute([$nom]);
    $comp = $stmt->fetch();

    if ($comp) {
        return (int) $comp['idCompetences'];
    }

    $stmt = $pdo->prepare('INSERT INTO COMPETENCES (nom) VALUES (?)');
    $stmt->execute([$nom]);
    return (int) $pdo->lastInsertId();
}

// Ajoute une compétence au profil de l'utilisateur connecté
function publier(PDO $pdo): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        erreur(405, 'Méthode non autorisée.');
    }

    $data       = json_decode(file_get_contents('php://input'), true) ?? [];
    $idUser     = (int) ($data['idUser'] ?? 0);
    $nom        = trim((string) ($data['nom'] ?? ''));
    $categorie  = categorieValide(trim((string) ($data['categorie'] ?? 'Autre')));
    $description = trim((string) ($data['description'] ?? ''));
    $coutHeure  = (int) ($data['coutHeure'] ?? 1);
    $format     = formatValide(trim((string) ($data['format'] ?? 'virtuel')));
    $support    = trim((string) ($data['support'] ?? ''));

    if ($idUser <= 0) {
        erreur(400, 'Utilisateur invalide.');
    }
    if ($nom === '') {
        erreur(400, 'Le titre de la compétence est requis.');
    }
    if ($coutHeure < 1 || $coutHeure > 3) {
        $coutHeure = 1;
    }
    if (mb_strlen($support) > 150) {
        $support = mb_substr($support, 0, 150);
    }

    $stmt = $pdo->prepare('SELECT idUser FROM USER WHERE idUser = ?');
    $stmt->execute([$idUser]);
    if (!$stmt->fetch()) {
        erreur(404, 'Utilisateur introuvable.');
    }

    $idComp = trouverOuCreerCompetence($pdo, $nom);

    $stmtExisting = $pdo->prepare('SELECT idEchange FROM ECHANGE WHERE idUser = ? AND idComp = ?');
    $stmtExisting->execute([$idUser, $idComp]);
    if ($stmtExisting->fetch()) {
        erreur(409, 'Tu proposes déjà cette compétence.');
    }

    $stmt = $pdo->prepare(
        'INSERT INTO ECHANGE (idUser, idComp, categorie, description, coutHeure, format, support) VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$idUser, $idComp, $categorie, $description ?: null, $coutHeure, $format, $support ?: null]);

    http_response_code(201);
    echo json_encode([
        'idEchange'     => (int) $pdo->lastInsertId(),
        'idCompetences' => $idComp,
        'competence'    => $nom,
        'categorie'     => $categorie,
        'description'   => $description,
        'coutHeure'     => $coutHeure,
        'format'        => $format,
        'support'       => $support,
    ]);
}

// Modifie une compétence publiée (titre, catégorie, description, coût)
function modifier(PDO $pdo): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        erreur(405, 'Méthode non autorisée.');
    }

    $data       = json_decode(file_get_contents('php://input'), true) ?? [];
    $idEchange  = (int) ($data['idEchange'] ?? 0);
    $idUser     = (int) ($data['idUser'] ?? 0);
    $nom        = trim((string) ($data['nom'] ?? ''));
    $categorie  = categorieValide(trim((string) ($data['categorie'] ?? 'Autre')));
    $description = trim((string) ($data['description'] ?? ''));
    $coutHeure  = (int) ($data['coutHeure'] ?? 1);
    $format     = formatValide(trim((string) ($data['format'] ?? 'virtuel')));
    $support    = trim((string) ($data['support'] ?? ''));

    if ($idEchange <= 0 || $idUser <= 0) {
        erreur(400, 'Paramètres invalides.');
    }
    if ($nom === '') {
        erreur(400, 'Le titre de la compétence est requis.');
    }
    if ($coutHeure < 1 || $coutHeure > 3) {
        $coutHeure = 1;
    }
    if (mb_strlen($support) > 150) {
        $support = mb_substr($support, 0, 150);
    }

    $stmt = $pdo->prepare('SELECT idUser FROM ECHANGE WHERE idEchange = ?');
    $stmt->execute([$idEchange]);
    $row = $stmt->fetch();

    if (!$row) {
        erreur(404, 'Compétence introuvable.');
    }
    if ((int) $row['idUser'] !== $idUser) {
        erreur(403, "Tu ne peux modifier que tes propres compétences.");
    }

    $idComp = trouverOuCreerCompetence($pdo, $nom);

    $stmt = $pdo->prepare(
        'UPDATE ECHANGE SET idComp = ?, categorie = ?, description = ?, coutHeure = ?, format = ?, support = ? WHERE idEchange = ?'
    );
    $stmt->execute([$idComp, $categorie, $description ?: null, $coutHeure, $format, $support ?: null, $idEchange]);

    echo json_encode([
        'idEchange'   => $idEchange,
        'competence'  => $nom,
        'categorie'   => $categorie,
        'description' => $description,
        'coutHeure'   => $coutHeure,
        'format'      => $format,
        'support'     => $support,
    ]);
}

// Supprime une compétence publiée (il doit toujours en rester au moins une)
function supprimer(PDO $pdo): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        erreur(405, 'Méthode non autorisée.');
    }

    $data      = json_decode(file_get_contents('php://input'), true) ?? [];
    $idEchange = (int) ($data['idEchange'] ?? 0);
    $idUser    = (int) ($data['idUser'] ?? 0);

    if ($idEchange <= 0 || $idUser <= 0) {
        erreur(400, 'Paramètres invalides.');
    }

    $stmt = $pdo->prepare('SELECT idUser FROM ECHANGE WHERE idEchange = ?');
    $stmt->execute([$idEchange]);
    $row = $stmt->fetch();

    if (!$row) {
        erreur(404, 'Compétence introuvable.');
    }
    if ((int) $row['idUser'] !== $idUser) {
        erreur(403, "Tu ne peux supprimer que tes propres compétences.");
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) AS nb FROM ECHANGE WHERE idUser = ?');
    $stmt->execute([$idUser]);
    if ((int) $stmt->fetch()['nb'] <= 1) {
        erreur(409, 'Tu dois garder au moins une compétence publiée.');
    }

    try {
        $stmt = $pdo->prepare('DELETE FROM ECHANGE WHERE idEchange = ?');
        $stmt->execute([$idEchange]);
    } catch (PDOException $e) {
        erreur(409, 'Impossible de supprimer : des sessions sont déjà liées à cette compétence.');
    }

    echo json_encode(['ok' => true]);
}
