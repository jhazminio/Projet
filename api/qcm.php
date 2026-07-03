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
    case 'creer':
        creer($pdo);
        break;
    case 'questions':
        questions($pdo);
        break;
    case 'repondre':
        repondre($pdo);
        break;
    default:
        erreur(404, 'Action inconnue.');
}

function chargerSession(PDO $pdo, int $idSession): array
{
    $stmt = $pdo->prepare(
        'SELECT s.*, e.idUser AS idEnseignant, e.coutHeure, c.nom AS competence
         FROM SESSION_ECHANGE s
         JOIN ECHANGE e ON e.idEchange = s.idEchange
         JOIN COMPETENCES c ON c.idCompetences = e.idComp
         WHERE s.idSession = ?'
    );
    $stmt->execute([$idSession]);
    $session = $stmt->fetch();
    if (!$session) {
        erreur(404, 'Session introuvable.');
    }
    return $session;
}

// L'enseignant crée (ou remplace) les questions du QCM pour une session terminée
function creer(PDO $pdo): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        erreur(405, 'Méthode non autorisée.');
    }

    $data      = json_decode(file_get_contents('php://input'), true) ?? [];
    $idSession = (int) ($data['idSession'] ?? 0);
    $idUser    = (int) ($data['idUser'] ?? 0);
    $questions = is_array($data['questions'] ?? null) ? $data['questions'] : [];

    $session = chargerSession($pdo, $idSession);
    if ((int) $session['idEnseignant'] !== $idUser) {
        erreur(403, "Seul·e l'enseignant·e peut créer le QCM.");
    }
    if (!in_array($session['statut'], ['terminee', 'validee'], true)) {
        erreur(409, "Le QCM ne peut être créé qu'une fois la session terminée.");
    }
    if (count($questions) === 0) {
        erreur(400, 'Ajoute au moins une question.');
    }

    try {
        $pdo->beginTransaction();

        $pdo->prepare('DELETE FROM QCM_QUESTION WHERE idSession = ?')->execute([$idSession]);

        $stmt = $pdo->prepare(
            'INSERT INTO QCM_QUESTION (idSession, enonce, optionA, optionB, optionC, optionD, bonneReponse)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );

        foreach ($questions as $q) {
            $enonce  = trim((string) ($q['enonce'] ?? ''));
            $options = is_array($q['options'] ?? null) ? array_values($q['options']) : [];
            $bonne   = strtoupper((string) ($q['bonneReponse'] ?? ''));

            if ($enonce === '' || count($options) !== 4 || !in_array($bonne, ['A', 'B', 'C', 'D'], true)) {
                throw new Exception('Question invalide.');
            }
            foreach ($options as $opt) {
                if (trim((string) $opt) === '') {
                    throw new Exception('Toutes les réponses doivent être remplies.');
                }
            }

            $stmt->execute([$idSession, $enonce, $options[0], $options[1], $options[2], $options[3], $bonne]);
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        erreur(400, $e->getMessage() ?: 'Erreur lors de la création du QCM.');
    }

    http_response_code(201);
    echo json_encode(['ok' => true]);
}

// Récupère les questions d'un QCM (sans révéler la bonne réponse)
function questions(PDO $pdo): void
{
    $idSession = (int) ($_GET['idSession'] ?? 0);
    if ($idSession <= 0) {
        erreur(400, 'idSession requis.');
    }

    $stmt = $pdo->prepare(
        'SELECT idQuestion, enonce, optionA, optionB, optionC, optionD
         FROM QCM_QUESTION WHERE idSession = ? ORDER BY idQuestion'
    );
    $stmt->execute([$idSession]);
    echo json_encode($stmt->fetchAll());
}

// L'apprenant répond au QCM ; 100% de bonnes réponses valide l'échange et verse les jetons
function repondre(PDO $pdo): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        erreur(405, 'Méthode non autorisée.');
    }

    $data      = json_decode(file_get_contents('php://input'), true) ?? [];
    $idSession = (int) ($data['idSession'] ?? 0);
    $idUser    = (int) ($data['idUser'] ?? 0);
    $reponses  = is_array($data['reponses'] ?? null) ? $data['reponses'] : [];

    $session = chargerSession($pdo, $idSession);
    if ((int) $session['idApprenant'] !== $idUser) {
        erreur(403, "Seul·e l'apprenant·e peut répondre à ce QCM.");
    }
    if ($session['qcmValide']) {
        erreur(409, 'Ce QCM est déjà validé.');
    }

    $stmt = $pdo->prepare('SELECT idQuestion, bonneReponse FROM QCM_QUESTION WHERE idSession = ?');
    $stmt->execute([$idSession]);
    $questions = $stmt->fetchAll();

    if (count($questions) === 0) {
        erreur(404, "Aucune question pour cette session.");
    }

    $correct = 0;
    foreach ($questions as $q) {
        $donnee = strtoupper((string) ($reponses[$q['idQuestion']] ?? ''));
        if ($donnee === $q['bonneReponse']) {
            $correct++;
        }
    }
    $total = count($questions);
    $score = (int) round(($correct / $total) * 100);

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('UPDATE SESSION_ECHANGE SET qcmScore = ? WHERE idSession = ?');
        $stmt->execute([$score, $idSession]);

        if ($score === 100) {
            $cout = (int) $session['coutHeure'];

            $stmt = $pdo->prepare('UPDATE SESSION_ECHANGE SET qcmValide = 1, statut = \'validee\' WHERE idSession = ?');
            $stmt->execute([$idSession]);

            $stmt = $pdo->prepare('INSERT INTO JETON_HISTORIQUE (idUser, montant, motif, idSession) VALUES (?, ?, ?, ?)');
            $stmt->execute([
                (int) $session['idEnseignant'],
                $cout,
                'Cours de ' . $session['competence'] . ' donné',
                $idSession,
            ]);
            $stmt->execute([
                (int) $session['idApprenant'],
                -$cout,
                'Cours de ' . $session['competence'] . ' reçu',
                $idSession,
            ]);
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        erreur(500, 'Erreur lors de la validation du QCM.');
    }

    echo json_encode(['score' => $score, 'correct' => $correct, 'total' => $total, 'valide' => $score === 100]);
}
