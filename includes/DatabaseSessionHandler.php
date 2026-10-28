<?php
/** Shared sessions with per-session MySQL locks to prevent concurrent lost updates. */
final class DatabaseSessionHandler implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface
{
    private ?string $lockName = null;
    public function __construct(private mysqli $db, private int $lifetime = 1800) {}
    public function open(string $path, string $name): bool { return true; }
    private function key(string $id): string { return hash('sha256', $id); }
    private function lock(string $id): void
    {
        $key = $this->key($id);
        if ($this->lockName === $key) { return; }
        $this->close();
        $stmt = $this->db->prepare('SELECT GET_LOCK(?, 10)');
        $stmt->bind_param('s', $key);
        $stmt->execute();
        $acquired = $stmt->get_result()->fetch_row()[0];
        $stmt->close();
        if ((int) $acquired !== 1) { throw new RuntimeException('Session lock timed out.'); }
        $this->lockName = $key;
    }
    public function close(): bool
    {
        if ($this->lockName !== null) {
            $stmt = $this->db->prepare('SELECT RELEASE_LOCK(?)');
            $stmt->bind_param('s', $this->lockName);
            $stmt->execute();
            $stmt->close();
            $this->lockName = null;
        }
        return true;
    }
    public function read(string $id): string|false
    {
        $this->lock($id);
        $key = $this->key($id);
        $stmt = $this->db->prepare('SELECT session_data FROM app_sessions WHERE session_key = ? AND expires_at > UNIX_TIMESTAMP()');
        $stmt->bind_param('s', $key);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ? $row['session_data'] : '';
    }
    public function write(string $id, string $data): bool
    {
        $this->lock($id);
        $key = $this->key($id);
        $expires = time() + $this->lifetime;
        $stmt = $this->db->prepare('INSERT INTO app_sessions (session_key, session_data, expires_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE session_data = ?, expires_at = ?');
        $stmt->bind_param('ssisi', $key, $data, $expires, $data, $expires);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
    public function destroy(string $id): bool
    {
        $this->lock($id);
        $key = $this->key($id);
        $stmt = $this->db->prepare('DELETE FROM app_sessions WHERE session_key = ?');
        $stmt->bind_param('s', $key);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
    public function gc(int $max_lifetime): int|false
    {
        $this->db->query('DELETE FROM app_sessions WHERE expires_at <= UNIX_TIMESTAMP()');
        return $this->db->affected_rows;
    }
    public function validateId(string $id): bool
    {
        return $this->read($id) !== '';
    }
    public function updateTimestamp(string $id, string $data): bool
    {
        return $this->write($id, $data);
    }
}
