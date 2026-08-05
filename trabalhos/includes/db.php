<?php
/*
 * Conexão com o banco MySQL "trabalhos".
 * Mesmo servidor MySQL da Intranet, mas em um banco separado —
 * não referencia nenhuma tabela da Intranet.
 *
 * Preencha $host / $user / $pass com as credenciais reais do seu MySQL.
 */
 
$host   = 'localhost';        // mesmo host do MySQL da Intranet, ajuste se for diferente
$dbname = 'trabalhos';        // banco dedicado a este sistema
$user   = 'root';
$pass   = 'BdP@25!';
 
try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    die('Erro ao conectar ao banco: ' . $e->getMessage());
}