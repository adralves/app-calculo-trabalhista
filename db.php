<?php
// Configurações do Banco de Dados (Altere conforme sua instalação)
$host = 'localhost';
$dbname = 'db_calculo';
$user = 'root';
$pass = '';

try {
    // Conexão PDO
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Auto-criação da tabela se não existir
    $sql = "CREATE TABLE IF NOT EXISTS rescisao_calculos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        uuid VARCHAR(36) NOT NULL UNIQUE,
        nome VARCHAR(255) NOT NULL,
        whatsapp VARCHAR(20) NOT NULL,
        dados_input LONGTEXT NOT NULL,
        dados_resultado LONGTEXT NOT NULL,
        data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB AUTO_INCREMENT=1000 DEFAULT CHARSET=utf8mb4;";
    
    $pdo->exec($sql);

    // Garante que o contador comece em 1000 mesmo se a tabela já existir
    $pdo->exec("ALTER TABLE rescisao_calculos AUTO_INCREMENT = 1000");

} catch (PDOException $e) {
    // Se falhar (ex: dados genéricos ainda não alterados), definimos pdo como null
    $pdo = null;
    error_log("Banco de dados não configurado: " . $e->getMessage());
}

/**
 * Função para gerar um UUID simplificado v4
 */
function generate_uuid() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}
