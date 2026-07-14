<?php
namespace Core;

use PDO;

/**
 * Modelo base: helpers de consulta con sentencias preparadas.
 */
abstract class Model
{
    protected PDO $db;

    public function __construct()
    {
        $this->db = Database::conexion();
    }

    protected function consultar(string $sql, array $params = []): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    protected function consultarUno(string $sql, array $params = []): ?array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $fila = $stmt->fetch();
        return $fila === false ? null : $fila;
    }

    protected function ejecutar(string $sql, array $params = []): int
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    protected function ultimoId(): int
    {
        return (int)$this->db->lastInsertId();
    }
}
