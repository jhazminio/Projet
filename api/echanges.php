<?php
require_once 'config.php';

$methode = $_SERVER['REQUEST_METHOD'];

if ($methode === 'GET') {
    $idUser = intval($_GET['idUser'] ?? 0);
    $conn   = getConnexion();

    if ($idUser > 0) {
        $stmt = $conn->prepare(
            'SELECT E.idEchange, E.dateEchange, C.nom AS competence
             FROM ECHANGE E
             JOIN COMPETENCES C ON E.idComp = C.idCompetences
             WHERE E.idUser = ?
             ORDER BY E.dateEchange DESC'
        );
        $stmt->bind_param('i', $idUser);
        $stmt->execute();
        $res = $stmt->get_result();
    } else {
        $res = $conn->query(
            'SELECT E.idEchange, E.dateEchange, U.prenomUser, U.nomUser, C.nom AS competence
             FROM ECHANGE E
             JOIN USER U ON E.idUser = U.idUser
             JOIN COMPETENCES C ON E.idComp = C.idCompetences
             ORDER BY E.dateEchange DESC'
        );
    }

    $liste = [];
    while ($row = $res->fetch_assoc()) $liste[] = $row;
    echo json_encode($liste);
    $conn->close();
    exit;
}

if ($methode === 'POST') {
    $data   = json_decode(file_get_contents('php://input'), true);
    $idUser = intval($data['idUser'] ?? 0);
    $idComp = intval($data['idComp'] ?? 0);

    if (!$idUser || !$idComp) {
        http_response_code(400);
        echo json_encode(['erreur' => 'idUser et idComp requis.']);
        exit;
    }

    $conn = getConnexion();
    $stmt = $conn->prepare('INSERT INTO ECHANGE (idUser, idComp) VALUES (?, ?)');
    $stmt->bind_param('ii', $idUser, $idComp);
    $stmt->execute();
    echo json_encode(['succes' => true, 'idEchange' => $conn->insert_id]);
    $conn->close();
    exit;
}

http_response_code(405);
echo json_encode(['erreur' => 'Méthode non autorisée.']);