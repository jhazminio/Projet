<?php
require_once 'config.php';

$methode = $_SERVER['REQUEST_METHOD'];

if ($methode === 'GET') {
    $conn  = getConnexion();
    $res   = $conn->query('SELECT idCompetences, nom FROM COMPETENCES ORDER BY nom');
    $liste = [];
    while ($row = $res->fetch_assoc()) $liste[] = $row;
    echo json_encode($liste);
    $conn->close();
    exit;
}

if ($methode === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $nom  = trim($data['nom'] ?? '');
    if (!$nom) { http_response_code(400); echo json_encode(['erreur' => 'Nom requis.']); exit; }

    $conn = getConnexion();
    $stmt = $conn->prepare('INSERT INTO COMPETENCES (nom) VALUES (?)');
    $stmt->bind_param('s', $nom);
    $stmt->execute();
    echo json_encode(['succes' => true, 'idCompetences' => $conn->insert_id]);
    $conn->close();
    exit;
}

http_response_code(405);
echo json_encode(['erreur' => 'Méthode non autorisée.']);