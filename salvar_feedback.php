<?php
// salvar_feedback.php

header('Content-Type: application/json; charset=utf-8');

try {
    // Caminho do banco (arquivo SQLite)
    $dataDir = __DIR__ . '/data';
    if (!is_dir($dataDir)) {
        mkdir($dataDir, 0775, true);
    }

    $dbPath = $dataDir . '/feedback.db';

    // Conexão com SQLite via PDO
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Cria tabela se ainda não existir
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS feedback (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            util TEXT,
            mensagem TEXT,
            pagina TEXT,
            user_agent TEXT,
            ip TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // Captura dados do POST
    $util      = isset($_POST['util']) ? substr(trim($_POST['util']), 0, 10) : null;
    $mensagem  = isset($_POST['mensagem']) ? substr(trim($_POST['mensagem']), 0, 4000) : null;
    $pagina    = isset($_POST['pagina']) ? substr(trim($_POST['pagina']), 0, 255) : null;
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    $ip        = $_SERVER['REMOTE_ADDR'] ?? null;

    // Validação básica (mesmo critério do JS)
    if ($util === null && ($mensagem === null || $mensagem === '')) {
        http_response_code(400);
        echo json_encode([
            'ok'    => false,
            'error' => 'Dados insuficientes para salvar o feedback.'
        ]);
        exit;
    }

    // INSERT
    $stmt = $pdo->prepare("
        INSERT INTO feedback (util, mensagem, pagina, user_agent, ip)
        VALUES (:util, :mensagem, :pagina, :user_agent, :ip)
    ");

    $stmt->execute([
        ':util'       => $util,
        ':mensagem'   => $mensagem,
        ':pagina'     => $pagina,
        ':user_agent' => $userAgent,
        ':ip'         => $ip,
    ]);

    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    // Em produção não exponha $e->getMessage()
    http_response_code(500);
    echo json_encode([
        'ok'    => false,
        'error' => 'Erro ao salvar o feedback.'
    ]);
}
