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
    case 'solde':
        solde($pdo);
        break;
    case 'historique':
        historique($pdo);
        break;
    default:
        erreur(404, 'Action inconnue.');
}

function solde(PDO $pdo): void
{
    $idUser = (int) ($_GET['idUser'] ?? 0);
    if ($idUser <= 0) {
        erreur(400, 'idUser requis.');
    }

    $stmt = $pdo->prepare('SELECT COALESCE(SUM(montant), 0) AS solde FROM JETON_HISTORIQUE WHERE idUser = ?');
    $stmt->execute([$idUser]);
    $solde = (int) $stmt->fetch()['solde'];

    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(CASE WHEN montant > 0 THEN montant ELSE 0 END), 0) AS gagnes,
                COALESCE(SUM(CASE WHEN montant < 0 THEN -montant ELSE 0 END), 0) AS depenses
         FROM JETON_HISTORIQUE
         WHERE idUser = ?
           AND MONTH(dateJeton) = MONTH(CURRENT_DATE())
           AND YEAR(dateJeton) = YEAR(CURRENT_DATE())"
    );
    $stmt->execute([$idUser]);
    $mois = $stmt->fetch();

    echo json_encode([
        'solde'         => $solde,
        'gagnesMois'    => (int) $mois['gagnes'],
        'depensesMois'  => (int) $mois['depenses'],
    ]);
}

function historique(PDO $pdo): void
{
    $idUser = (int) ($_GET['idUser'] ?? 0);
    if ($idUser <= 0) {
        erreur(400, 'idUser requis.');
    }

    $stmt = $pdo->prepare(
        'SELECT idJeton, montant, motif, dateJeton
         FROM JETON_HISTORIQUE
         WHERE idUser = ?
         ORDER BY dateJeton DESC'
    );
    $stmt->execute([$idUser]);
    echo json_encode($stmt->fetchAll());
}
