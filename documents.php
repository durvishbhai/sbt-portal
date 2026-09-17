<?php
require_once __DIR__ . '/includes/bootstrap.php';

$user = require_login();
$pdo = db();

$docTypes = [
    'id_card' => 'ID Card',
    'voter_id' => 'Voter ID',
    'team_document' => 'Team Document',
    'certificate' => 'Certificate',
    'other' => 'Other',
];
$allowedExt = ['pdf', 'jpg', 'jpeg', 'png'];
$allowedMime = ['application/pdf', 'image/jpeg', 'image/png'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload') {
    require_write_access();
    verify_csrf();

    $title = trim($_POST['title'] ?? '');
    $docType = $_POST['doc_type'] ?? 'other';
    if (!array_key_exists($docType, $docTypes)) {
        $docType = 'other';
    }

    if ($title === '') {
        flash('error', 'Please give the document a title.');
        redirect('documents.php');
    }

    if (empty($_FILES['document']['name']) || $_FILES['document']['error'] === UPLOAD_ERR_NO_FILE) {
        flash('error', 'Please choose a file to upload.');
        redirect('documents.php');
    }

    $file = $_FILES['document'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        flash('error', 'Upload failed. Please try again.');
        redirect('documents.php');
    }
    if ($file['size'] > MAX_UPLOAD_BYTES) {
        flash('error', 'File is too large. Maximum size is 5 MB.');
        redirect('documents.php');
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($ext, $allowedExt, true) || !in_array($mime, $allowedMime, true)) {
        flash('error', 'Only PDF, JPG and PNG files are allowed.');
        redirect('documents.php');
    }

    $userDir = UPLOAD_DIR . '/documents/' . $user['id'];
    if (!is_dir($userDir)) {
        mkdir($userDir, 0750, true);
    }
    $storedName = bin2hex(random_bytes(16)) . '.' . $ext;
    $destination = $userDir . '/' . $storedName;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        flash('error', 'Could not save the uploaded file.');
        redirect('documents.php');
    }

    $relativePath = 'uploads/documents/' . $user['id'] . '/' . $storedName;
    $stmt = $pdo->prepare(
        'INSERT INTO documents (user_id, team_id, doc_type, title, file_path, file_size, status)
         VALUES (?, ?, ?, ?, ?, ?, "pending")'
    );
    $stmt->execute([$user['id'], $user['team_id'], $docType, $title, $relativePath, $file['size']]);
    log_activity($user['id'], 'document_uploaded', $title);
    flash('success', 'Document uploaded and awaiting verification.');
    redirect('documents.php');
}

$statusFilter = $_GET['status'] ?? '';
$allowedStatuses = ['pending', 'verified', 'rejected'];
$sql = "SELECT d.*, u.full_name, v.full_name AS verified_by_name
        FROM documents d
        JOIN users u ON u.id = d.user_id
        LEFT JOIN users v ON v.id = d.verified_by";
$params = [];
$conditions = [];
if (!is_admin($user)) {
    $conditions[] = 'd.user_id = ?';
    $params[] = $user['id'];
}
if (in_array($statusFilter, $allowedStatuses, true)) {
    $conditions[] = 'd.status = ?';
    $params[] = $statusFilter;
}
if ($conditions) {
    $sql .= ' WHERE ' . implode(' AND ', $conditions);
}
$sql .= ' ORDER BY d.uploaded_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$documents = $stmt->fetchAll();

$pageTitle = 'Document Vault';
require __DIR__ . '/includes/header.php';
?>
<div class="page-header">
  <div>
    <h1>Secure Document Vault</h1>
    <p class="subtitle">Upload ID cards, voter IDs and team documents for verification.</p>
  </div>
</div>

<?php if (!is_view_only($user)): ?>
<div class="card" style="max-width:520px;">
  <h3>Upload a document</h3>
  <form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="upload">
    <label for="title">Title</label>
    <input type="text" id="title" name="title" required>

    <label for="doc_type">Document type</label>
    <select id="doc_type" name="doc_type">
      <?php foreach ($docTypes as $val => $label): ?>
        <option value="<?= e($val) ?>"><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>

    <label for="document">File (PDF, JPG, PNG — max 5MB)</label>
    <input type="file" id="document" name="document" accept=".pdf,.jpg,.jpeg,.png" required>

    <button type="submit" class="btn">Upload</button>
  </form>
</div>
<?php endif; ?>

<div class="card no-print">
  <a href="documents.php" class="btn-sm btn <?= $statusFilter === '' ? '' : 'btn-secondary' ?>">All</a>
  <a href="documents.php?status=pending" class="btn-sm btn <?= $statusFilter === 'pending' ? '' : 'btn-secondary' ?>">Pending</a>
  <a href="documents.php?status=verified" class="btn-sm btn <?= $statusFilter === 'verified' ? '' : 'btn-secondary' ?>">Verified</a>
  <a href="documents.php?status=rejected" class="btn-sm btn <?= $statusFilter === 'rejected' ? '' : 'btn-secondary' ?>">Rejected</a>
</div>

<div class="card">
  <div class="table-wrap">
  <?php if (!$documents): ?>
    <p class="empty-state">No documents found.</p>
  <?php else: ?>
  <table>
    <tr><th>Title</th><th>Type</th><?php if (is_admin($user)): ?><th>Owner</th><?php endif; ?><th>Status</th><th>Uploaded</th><th>Actions</th></tr>
    <?php foreach ($documents as $doc): ?>
      <tr>
        <td><?= e($doc['title']) ?></td>
        <td><?= e($docTypes[$doc['doc_type']] ?? $doc['doc_type']) ?></td>
        <?php if (is_admin($user)): ?><td><?= e($doc['full_name']) ?></td><?php endif; ?>
        <td><span class="badge badge-<?= e($doc['status']) ?>"><?= e(ucfirst($doc['status'])) ?></span>
          <?php if ($doc['status'] === 'rejected' && $doc['rejection_reason']): ?>
            <br><span style="font-size:11px;color:#6b7280;"><?= e($doc['rejection_reason']) ?></span>
          <?php endif; ?>
        </td>
        <td><?= format_datetime($doc['uploaded_at']) ?></td>
        <td>
          <a class="btn btn-sm btn-secondary" href="<?= base_path() . '/' . e($doc['file_path']) ?>" target="_blank" rel="noopener">View</a>
          <?php if (is_admin($user) && $doc['status'] === 'pending'): ?>
            <form method="post" action="document_verify.php" style="display:inline;">
              <?= csrf_field() ?>
              <input type="hidden" name="document_id" value="<?= (int) $doc['id'] ?>">
              <input type="hidden" name="decision" value="verify">
              <button type="submit" class="btn btn-sm">Verify</button>
            </form>
            <form method="post" action="document_verify.php" style="display:inline;" data-confirm="Reject this document?">
              <?= csrf_field() ?>
              <input type="hidden" name="document_id" value="<?= (int) $doc['id'] ?>">
              <input type="hidden" name="decision" value="reject">
              <button type="submit" class="btn btn-sm btn-danger">Reject</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
