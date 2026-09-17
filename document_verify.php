<?php
require_once __DIR__ . '/includes/bootstrap.php';

$user = require_role(ADMIN_ROLES);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('documents.php');
}
verify_csrf();

$documentId = (int) ($_POST['document_id'] ?? 0);
$decision = $_POST['decision'] ?? '';

$pdo = db();
$stmt = $pdo->prepare('SELECT * FROM documents WHERE id = ?');
$stmt->execute([$documentId]);
$document = $stmt->fetch();

if (!$document || $document['status'] !== 'pending') {
    flash('error', 'Document not found or already reviewed.');
    redirect('documents.php');
}

if ($decision === 'verify') {
    $pdo->prepare('UPDATE documents SET status = "verified", verified_by = ?, verified_at = NOW() WHERE id = ?')
        ->execute([$user['id'], $documentId]);
    log_activity($user['id'], 'document_verified', $document['title']);
    flash('success', 'Document verified.');
} elseif ($decision === 'reject') {
    $pdo->prepare('UPDATE documents SET status = "rejected", verified_by = ?, verified_at = NOW(), rejection_reason = ? WHERE id = ?')
        ->execute([$user['id'], 'Did not meet verification requirements', $documentId]);
    log_activity($user['id'], 'document_rejected', $document['title']);
    flash('success', 'Document rejected.');
}

redirect('documents.php');
