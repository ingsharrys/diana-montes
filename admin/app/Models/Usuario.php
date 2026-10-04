<?php
namespace Models;

use Core\Model;

class Usuario extends Model
{
    public function porEmail(string $email): ?array
    {
        return $this->consultarUno(
            'SELECT * FROM usuarios WHERE email = :email AND activo = 1 LIMIT 1',
            ['email' => $email]
        );
    }

    public function estaBloqueado(array $usuario): bool
    {
        return $usuario['bloqueado_hasta'] !== null
            && strtotime($usuario['bloqueado_hasta']) > time();
    }

    /** Suma un intento fallido y bloquea al llegar al límite. */
    public function intentoFallido(int $id): void
    {
        $this->ejecutar('UPDATE usuarios SET intentos_fallidos = intentos_fallidos + 1 WHERE id = :id', ['id' => $id]);
        $u = $this->consultarUno('SELECT intentos_fallidos FROM usuarios WHERE id = :id', ['id' => $id]);
        if ($u && (int)$u['intentos_fallidos'] >= MAX_LOGIN_ATTEMPTS) {
            $this->ejecutar(
                'UPDATE usuarios SET bloqueado_hasta = DATE_ADD(NOW(), INTERVAL :min MINUTE), intentos_fallidos = 0 WHERE id = :id',
                ['min' => LOCK_MINUTES, 'id' => $id]
            );
        }
    }

    public function loginExitoso(int $id): void
    {
        $this->ejecutar(
            'UPDATE usuarios SET intentos_fallidos = 0, bloqueado_hasta = NULL, ultimo_acceso = NOW() WHERE id = :id',
            ['id' => $id]
        );
    }

    /* ================= Gestión de usuarios (módulo Usuarios) ================= */

    public function listar(): array
    {
        return $this->consultar(
            "SELECT u.id, u.nombre, u.email, u.telefono, u.rol, u.codigo_ref, u.activo,
                    z.nombre AS zona,
                    (SELECT COUNT(*) FROM simpatizantes s WHERE s.lider_id = u.id) AS vinculados,
                    (SELECT m.cantidad FROM metas m WHERE m.usuario_id = u.id AND m.periodo = 'campana' LIMIT 1) AS meta
             FROM usuarios u
             LEFT JOIN zonas z ON z.id = u.zona_id
             ORDER BY u.activo DESC, FIELD(u.rol,'direccion','coordinador','lider','digitador'), u.nombre"
        );
    }

    public function existeCodigoRef(string $codigo): bool
    {
        return $this->consultarUno('SELECT id FROM usuarios WHERE codigo_ref = :c LIMIT 1', ['c' => $codigo]) !== null;
    }

    public function existeEmail(string $email): bool
    {
        return $this->consultarUno('SELECT id FROM usuarios WHERE email = :e LIMIT 1', ['e' => $email]) !== null;
    }

    /** Genera un codigo_ref único: nombre-slug + sufijo aleatorio. */
    public function generarCodigoRef(string $nombre): string
    {
        $base = strtolower(trim(preg_replace('/[^a-z]+/i', '-', iconv('UTF-8','ASCII//TRANSLIT',$nombre)), '-'));
        $base = substr(explode('-', $base)[0] ?: 'lider', 0, 12);
        do {
            $codigo = $base . '-gz' . str_pad((string)random_int(1, 999), 2, '0', STR_PAD_LEFT);
        } while ($this->consultarUno('SELECT id FROM usuarios WHERE codigo_ref = :c', ['c' => $codigo]));
        return $codigo;
    }

    public function crear(array $d): int
    {
        $this->ejecutar(
            'INSERT INTO usuarios (nombre, email, telefono, password_hash, rol, zona_id, coordinador_id, codigo_ref)
             VALUES (:nombre, :email, :telefono, :hash, :rol, :zona_id, :coordinador_id, :codigo_ref)',
            $d
        );
        return $this->ultimoId();
    }

    public function asignarMeta(int $usuarioId, int $cantidad): void
    {
        $this->ejecutar(
            "INSERT INTO metas (usuario_id, periodo, cantidad) VALUES (:u, 'campana', :c)
             ON DUPLICATE KEY UPDATE cantidad = :c2",
            ['u' => $usuarioId, 'c' => $cantidad, 'c2' => $cantidad]
        );
    }

    public function cambiarEstado(int $id, int $activo): void
    {
        $this->ejecutar('UPDATE usuarios SET activo = :a WHERE id = :id', ['a' => $activo, 'id' => $id]);
    }

    public function resetClave(int $id, string $hash): void
    {
        $this->ejecutar('UPDATE usuarios SET password_hash = :h, intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id = :id',
            ['h' => $hash, 'id' => $id]);
    }

    /* ================= Avance y ranking del equipo (dashboard) ================= */

    /**
     * "Mi avance": vinculados, meta y puesto entre los miembros del equipo
     * con enlace de invitación (la dirección no compite: es la red raíz).
     */
    public function avance(int $id): array
    {
        $vinculados = (int)($this->consultarUno(
            'SELECT COUNT(*) c FROM simpatizantes WHERE lider_id = :id', ['id' => $id]
        )['c'] ?? 0);

        $meta = (int)($this->consultarUno(
            "SELECT cantidad FROM metas WHERE usuario_id = :id AND periodo = 'campana' LIMIT 1", ['id' => $id]
        )['cantidad'] ?? 0);

        $equipo = $this->consultarUno(
            "SELECT COUNT(*) AS total,
                    SUM(t.vinculados > :v) AS por_encima
             FROM (SELECT (SELECT COUNT(*) FROM simpatizantes s WHERE s.lider_id = u.id) AS vinculados
                   FROM usuarios u
                   WHERE u.activo = 1 AND u.codigo_ref IS NOT NULL AND u.rol <> 'direccion') t",
            ['v' => $vinculados]
        );

        return [
            'vinculados' => $vinculados,
            'meta'       => $meta,
            'progreso'   => $meta > 0 ? min(100, (int)round($vinculados * 100 / $meta)) : null,
            'puesto'     => (int)($equipo['por_encima'] ?? 0) + 1,
            'equipo'     => (int)($equipo['total'] ?? 0),
        ];
    }

    /** Top del equipo por simpatizantes vinculados (sin la dirección). */
    public function rankingEquipo(int $limite = 5): array
    {
        return $this->consultar(
            "SELECT u.nombre, u.rol,
                    (SELECT COUNT(*) FROM simpatizantes s WHERE s.lider_id = u.id) AS vinculados,
                    (SELECT m.cantidad FROM metas m WHERE m.usuario_id = u.id AND m.periodo = 'campana' LIMIT 1) AS meta
             FROM usuarios u
             WHERE u.activo = 1 AND u.codigo_ref IS NOT NULL AND u.rol <> 'direccion'
             ORDER BY vinculados DESC, u.nombre
             LIMIT " . (int)$limite
        );
    }

    public function porId(int $id): ?array
    {
        return $this->consultarUno('SELECT * FROM usuarios WHERE id = :id', ['id' => $id]);
    }
}
