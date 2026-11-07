<?php
declare(strict_types=1);
namespace McRelish\Controllers;

use McRelish\Core\{Database, HttpException, Request, Response, Time, Uuid, Validate};
use McRelish\Services\Auth;

final class StaffController
{
    private const ROLES = ['admin', 'staff', 'dispatcher'];

    private function role(mixed $v): string
    {
        if (!in_array($v, self::ROLES, true)) throw new HttpException('Choose a valid role.', 422);
        return $v;
    }

    public function index(): void
    {
        Auth::requireRole('admin');
        $byUser = [];
        foreach (Database::all('SELECT r.user_id, r.role, u.email FROM user_roles r JOIN users u ON u.id = r.user_id ORDER BY u.email, r.role') as $row) {
            $byUser[$row['user_id']] ??= ['user_id' => $row['user_id'], 'email' => $row['email'], 'roles' => []];
            $byUser[$row['user_id']]['roles'][] = $row['role'];
        }
        Response::json(['staff' => array_values($byUser)]);
    }

    public function add(): void
    {
        Auth::requireRole('admin');
        $in = Request::json();
        $email = Validate::email($in['email'] ?? null);
        $role = $this->role($in['role'] ?? null);
        $user = Database::one('SELECT id FROM users WHERE email = ?', [$email]) ?? throw new HttpException('No account with that email. Ask them to sign up on the site first.', 404);
        if (Database::one('SELECT 1 AS x FROM user_roles WHERE user_id = ? AND role = ?', [$user['id'], $role])) throw new HttpException('That account already has this role.', 409);
        Database::run('INSERT INTO user_roles (user_id, role) VALUES (?, ?)', [$user['id'], $role]);
        Response::json(['ok' => true], 201);
    }

    public function remove(): void
    {
        $me = Auth::requireRole('admin');
        $in = Request::json();
        $userId = Validate::uuid($in['userId'] ?? null, 'Choose a staff member.');
        $role = $this->role($in['role'] ?? null);
        if ($userId === $me['id'] && $role === 'admin') throw new HttpException("You can't remove your own admin role.", 409);
        Database::run('DELETE FROM user_roles WHERE user_id = ? AND role = ?', [$userId, $role]);
        Response::json(['ok' => true]);
    }

    public function auditAdd(): void
    {
        $me = Auth::requireRole(...self::ROLES);
        $in = Request::json();
        $action = Validate::str($in['action'] ?? null, 1, 80, 'Invalid action.');
        $details = is_array($in['details'] ?? null) ? $in['details'] : [];
        $json = json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR) ?: '{}';
        Database::run('INSERT INTO audit_log (id, actor_id, actor_email, action, details) VALUES (?, ?, ?, ?, ?)', [Uuid::v4(), $me['id'], $me['email'], $action, mb_substr($json, 0, 20000)]);
        Response::json(['ok' => true], 201);
    }

    public function auditList(): void
    {
        Auth::requireRole('admin');
        $limit = Validate::int(Request::query('limit', '50'), 1, 200, 'Invalid limit.');
        $rows = Database::all('SELECT id, actor_email, action, details, created_at FROM audit_log ORDER BY created_at DESC, id DESC LIMIT ' . $limit);
        Response::json(['logs' => array_map(fn ($r) => [
            'id' => $r['id'], 'actor_email' => $r['actor_email'], 'action' => $r['action'],
            'details' => json_decode((string) $r['details'], true) ?? new \stdClass(), 'created_at' => Time::iso($r['created_at']),
        ], $rows)]);
    }
}
