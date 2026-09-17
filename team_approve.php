<?php
require_once __DIR__ . '/includes/bootstrap.php';

$user = require_role(ADMIN_ROLES);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('teams.php');
}
verify_csrf();

if (is_lockdown_active() && !is_admin($user)) {
    flash('error', 'Portal is in emergency lockdown.');
    redirect('teams.php');
}

$teamId = (int) ($_POST['team_id'] ?? 0);
$decision = $_POST['decision'] ?? '';

$pdo = db();
$stmt = $pdo->prepare('SELECT * FROM teams WHERE id = ?');
$stmt->execute([$teamId]);
$team = $stmt->fetch();

if (!$team || $team['status'] !== 'pending') {
    flash('error', 'Team not found or already reviewed.');
    redirect('teams.php');
}

if ($decision === 'approve') {
    $pdo->prepare('UPDATE teams SET status = "approved", approved_by = ?, approved_at = NOW() WHERE id = ?')
        ->execute([$user['id'], $teamId]);
    if ($team['created_by']) {
        $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ? AND status = 'pending'")
            ->execute([$team['created_by']]);
    }
    log_activity($user['id'], 'team_approved', $team['name']);
    flash('success', 'Team "' . $team['name'] . '" approved.');
} elseif ($decision === 'reject') {
    $pdo->prepare('UPDATE teams SET status = "rejected", approved_by = ?, approved_at = NOW() WHERE id = ?')
        ->execute([$user['id'], $teamId]);
    log_activity($user['id'], 'team_rejected', $team['name']);
    flash('success', 'Team "' . $team['name'] . '" rejected.');
} else {
    flash('error', 'Unknown decision.');
}

redirect('teams.php');
