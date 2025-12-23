<?php
// db.php
// Conexão centralizada com o SQLite (data/feedback.db) + criação de schema básico.
// Mantém o banco num diretório "data" ao lado dos arquivos PHP.

declare(strict_types=1);

function db_path(): string {
    $dataDir = __DIR__ . '/data';
    if (!is_dir($dataDir)) {
        mkdir($dataDir, 0775, true);
    }
    return $dataDir . '/feedback.db';
}

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $pdo = new PDO('sqlite:' . db_path());
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Segurança/consistência (sem impacto em read-only):
    $pdo->exec('PRAGMA foreign_keys = ON;');
    $pdo->exec('PRAGMA journal_mode = WAL;');

    ensure_schema($pdo);
    return $pdo;
}

function ensure_schema(PDO $pdo): void {
    // Tabela de feedbacks (mantém compatibilidade com seu salvar_feedback.php)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS feedback (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            util TEXT NULL,
            mensagem TEXT NOT NULL,
            pagina TEXT NULL,
            user_agent TEXT NULL,
            ip TEXT NULL,
            created_at TEXT NOT NULL DEFAULT (datetime('now'))
        );
    ");

    // Índices para o painel
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_feedback_created_at ON feedback(created_at);");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_feedback_util ON feedback(util);");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_feedback_pagina ON feedback(pagina);");

    // Tabela de administradores (login do painel)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS admins (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            is_active INTEGER NOT NULL DEFAULT 1,
            created_at TEXT NOT NULL DEFAULT (datetime('now')),
            updated_at TEXT NULL
        );
    ");

    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_admins_username ON admins(username);");
}
