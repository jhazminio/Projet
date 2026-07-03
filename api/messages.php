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
    case 'conversations':
        conversations($pdo);
        break;
    case 'historique':
        historique($pdo);
        break;
    case 'envoyer':
        envoyer($pdo);
        break;
    default:
        erreur(404, 'Action inconnue.');
}

// Liste des personnes avec qui l'utilisateur connecté a déjà échangé des messages
function conversations(PDO $pdo): void
{
    $idUser = (int) ($_GET['idUser'] ?? 0);
    if ($idUser <= 0) {
        erreur(400, 'idUser requis.');
    }

    $stmt = $pdo->prepare(
        'SELECT u.idUser, u.nomUser, u.prenomUser, u.photo,
                (SELECT contenu FROM MESSAGE m
                   WHERE (m.idEnvoyeur = :me AND m.idReceveur = u.idUser)
                      OR (m.idEnvoyeur = u.idUser AND m.idReceveur = :me)
                   ORDER BY m.dateMessage DESC LIMIT 1) AS dernierMessage,
                (SELECT dateMessage FROM MESSAGE m
                   WHERE (m.idEnvoyeur = :me AND m.idReceveur = u.idUser)
                      OR (m.idEnvoyeur = u.idUser AND m.idReceveur = :me)
                   ORDER BY m.dateMessage DESC LIMIT 1) AS derniereDate
         FROM USER u
         WHERE u.idUser != :me
           AND EXISTS (
                SELECT 1 FROM MESSAGE m
                WHERE (m.idEnvoyeur = :me AND m.idReceveur = u.idUser)
                   OR (m.idEnvoyeur = u.idUser AND m.idReceveur = :me)
           )
         ORDER BY derniereDate DESC'
    );
    $stmt->execute(['me' => $idUser]);
    echo json_encode($stmt->fetchAll());
}

// Historique des messages entre l'utilisateur connecté et un autre utilisateur
function historique(PDO $pdo): void
{
    $idUser  = (int) ($_GET['idUser'] ?? 0);
    $idAutre = (int) ($_GET['idAutre'] ?? 0);

    if ($idUser <= 0 || $idAutre <= 0) {
        erreur(400, 'Paramètres invalides.');
    }

    $stmt = $pdo->prepare(
        'SELECT idMessage, idEnvoyeur, idReceveur, contenu, dateMessage
         FROM MESSAGE
         WHERE (idEnvoyeur = ? AND idReceveur = ?) OR (idEnvoyeur = ? AND idReceveur = ?)
         ORDER BY dateMessage ASC'
    );
    $stmt->execute([$idUser, $idAutre, $idAutre, $idUser]);
    echo json_encode($stmt->fetchAll());
}

// Envoie un nouveau message
function envoyer(PDO $pdo): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        erreur(405, 'Méthode non autorisée.');
    }

    $data        = json_decode(file_get_contents('php://input'), true) ?? [];
    $idEnvoyeur  = (int) ($data['idEnvoyeur'] ?? 0);
    $idReceveur  = (int) ($data['idReceveur'] ?? 0);
    $contenu     = trim((string) ($data['contenu'] ?? ''));

    if ($idEnvoyeur <= 0 || $idReceveur <= 0 || $contenu === '') {
        erreur(400, 'Paramètres invalides.');
    }
    if ($idEnvoyeur === $idReceveur) {
        erreur(400, 'Tu ne peux pas t\'envoyer un message à toi-même.');
    }

    $stmt = $pdo->prepare('INSERT INTO MESSAGE (idEnvoyeur, idReceveur, contenu) VALUES (?, ?, ?)');
    $stmt->execute([$idEnvoyeur, $idReceveur, $contenu]);

    $idMessage = (int) $pdo->lastInsertId();
    $stmt = $pdo->prepare('SELECT idMessage, idEnvoyeur, idReceveur, contenu, dateMessage FROM MESSAGE WHERE idMessage = ?');
    $stmt->execute([$idMessage]);

    http_response_code(201);
    echo json_encode($stmt->fetch());
}
