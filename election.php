<?php
require_once __DIR__ . '/includes/bootstrap.php';

$user = require_login();
$pdo = db();
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM elections WHERE id = ?');
$stmt->execute([$id]);
$election = $stmt->fetch();
if (!$election) {
    http_response_code(404);
    die('Election not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_write_access();
    verify_csrf();
    $formAction = $_POST['form_action'] ?? '';

    if ($formAction === 'add_candidate') {
        require_role(ADMIN_ROLES);
        $name = trim($_POST['candidate_name'] ?? '');
        $statement = trim($_POST['statement'] ?? '');
        if ($name === '') {
            flash('error', 'Candidate name is required.');
        } else {
            $pdo->prepare('INSERT INTO election_candidates (election_id, candidate_name, statement) VALUES (?, ?, ?)')
                ->execute([$id, $name, $statement ?: null]);
            log_activity($user['id'], 'election_candidate_added', $name . ' (' . $election['title'] . ')');
            flash('success', 'Candidate added.');
        }
    } elseif ($formAction === 'set_status') {
        require_role(ADMIN_ROLES);
        $newStatus = $_POST['status'] ?? '';
        if (in_array($newStatus, ['draft', 'active', 'closed'], true)) {
            $pdo->prepare('UPDATE elections SET status = ? WHERE id = ?')->execute([$newStatus, $id]);
            log_activity($user['id'], 'election_status_changed', $election['title'] . ' -> ' . $newStatus);
            flash('success', 'Election status updated.');
        }
    } elseif ($formAction === 'vote') {
        if ($election['status'] !== 'active') {
            flash('error', 'Voting is not currently open for this election.');
        } elseif (strtotime($election['ends_at']) < time() || strtotime($election['starts_at']) > time()) {
            flash('error', 'Voting window is not currently open.');
        } else {
            $candidateId = (int) ($_POST['candidate_id'] ?? 0);
            $candStmt = $pdo->prepare('SELECT id FROM election_candidates WHERE id = ? AND election_id = ?');
            $candStmt->execute([$candidateId, $id]);
            if (!$candStmt->fetch()) {
                flash('error', 'Invalid candidate.');
            } else {
                try {
                    $pdo->beginTransaction();
                    $pdo->prepare('INSERT INTO election_votes (election_id, candidate_id, voter_id) VALUES (?, ?, ?)')
                        ->execute([$id, $candidateId, $user['id']]);
                    $pdo->prepare('UPDATE election_candidates SET votes_count = votes_count + 1 WHERE id = ?')
                        ->execute([$candidateId]);
                    $pdo->commit();
                    log_activity($user['id'], 'election_vote_cast', $election['title']);
                    flash('success', 'Your vote has been recorded. Thank you!');
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    if ($e->getCode() === '23000') {
                        flash('error', 'You have already voted in this election.');
                    } else {
                        throw $e;
                    }
                }
            }
        }
    }
    redirect('election.php?id=' . $id);
}

$candidates = $pdo->prepare('SELECT * FROM election_candidates WHERE election_id = ? ORDER BY votes_count DESC, candidate_name');
$candidates->execute([$id]);
$candidates = $candidates->fetchAll();

$voteCheck = $pdo->prepare('SELECT candidate_id FROM election_votes WHERE election_id = ? AND voter_id = ?');
$voteCheck->execute([$id, $user['id']]);
$myVoteCandidateId = $voteCheck->fetchColumn();

$totalVotes = array_sum(array_column($candidates, 'votes_count'));
$votingOpen = $election['status'] === 'active'
    && strtotime($election['starts_at']) <= time()
    && strtotime($election['ends_at']) >= time();
$showResults = $election['status'] === 'closed' || $myVoteCandidateId !== false || is_admin($user);

$pageTitle = $election['title'];
require __DIR__ . '/includes/header.php';
?>
<div class="page-header">
  <div>
    <h1><?= e($election['title']) ?></h1>
    <p class="subtitle"><?= e($election['description']) ?></p>
  </div>
  <span class="badge badge-<?= e($election['status']) ?>"><?= e(ucfirst($election['status'])) ?></span>
</div>

<p class="hint">Voting window: <?= format_datetime($election['starts_at']) ?> &rarr; <?= format_datetime($election['ends_at']) ?></p>

<?php if (is_admin($user)): ?>
<div class="card no-print">
  <h3>Manage election</h3>
  <form method="post" style="display:inline-block;margin-right:10px;">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $id ?>">
    <input type="hidden" name="form_action" value="set_status">
    <input type="hidden" name="status" value="active">
    <button type="submit" class="btn btn-sm" <?= $election['status'] === 'active' ? 'disabled' : '' ?>>Activate</button>
  </form>
  <form method="post" style="display:inline-block;">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $id ?>">
    <input type="hidden" name="form_action" value="set_status">
    <input type="hidden" name="status" value="closed">
    <button type="submit" class="btn btn-sm btn-danger" <?= $election['status'] === 'closed' ? 'disabled' : '' ?>>Close &amp; publish results</button>
  </form>

  <h4 style="margin-top:20px;">Add candidate</h4>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $id ?>">
    <input type="hidden" name="form_action" value="add_candidate">
    <div class="field-row">
      <div>
        <label for="candidate_name">Name</label>
        <input type="text" id="candidate_name" name="candidate_name" required>
      </div>
      <div>
        <label for="statement">Statement (optional)</label>
        <input type="text" id="statement" name="statement">
      </div>
    </div>
    <button type="submit" class="btn btn-sm">Add candidate</button>
  </form>
</div>
<?php endif; ?>

<div class="card">
  <h3>Candidates</h3>
  <?php if (!$candidates): ?>
    <p class="empty-state">No candidates added yet.</p>
  <?php else: ?>
    <?php foreach ($candidates as $cand): ?>
      <div style="padding:12px 0; border-bottom:1px solid #e6e8eb;">
        <strong><?= e($cand['candidate_name']) ?></strong>
        <?php if ($myVoteCandidateId == $cand['id']): ?><span class="badge badge-approved">Your vote</span><?php endif; ?>
        <?php if ($cand['statement']): ?><p style="margin:4px 0; color:#6b7280; font-size:13px;"><?= e($cand['statement']) ?></p><?php endif; ?>

        <?php if ($showResults): ?>
          <?php $pct = $totalVotes > 0 ? round($cand['votes_count'] / $totalVotes * 100) : 0; ?>
          <div style="background:#e6e8eb; border-radius:6px; height:10px; margin-top:6px;">
            <div style="background:#4caf1f; width:<?= $pct ?>%; height:10px; border-radius:6px;"></div>
          </div>
          <span style="font-size:12px; color:#6b7280;"><?= (int) $cand['votes_count'] ?> vote(s) &middot; <?= $pct ?>%</span>
        <?php elseif ($votingOpen && $myVoteCandidateId === false): ?>
          <form method="post" style="margin-top:6px;">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $id ?>">
            <input type="hidden" name="form_action" value="vote">
            <input type="hidden" name="candidate_id" value="<?= (int) $cand['id'] ?>">
            <button type="submit" class="btn btn-sm">Vote for <?= e($cand['candidate_name']) ?></button>
          </form>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
    <?php if ($showResults): ?><p class="hint">Total votes cast: <?= $totalVotes ?></p><?php endif; ?>
  <?php endif; ?>
</div>

<a class="btn btn-secondary no-print" href="<?= base_path() ?>/elections.php">&larr; Back to elections</a>
<?php require __DIR__ . '/includes/footer.php'; ?>
