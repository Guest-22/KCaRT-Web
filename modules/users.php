<?php
$editId = query_id('edit');
$editing = null;
if ($editId > 0) {
    $statement = $pdo->prepare('SELECT * FROM tbl_user WHERE user_id=?');
    $statement->execute([$editId]);
    $editing = $statement->fetch();
}
$query = trim(is_string($_GET['q'] ?? null) ? $_GET['q'] : '');
$usersQuery = 'SELECT user_id,first_name,middle_name,last_name,sex,date_of_birth,contact_no,email,role,username,user_status,created_at FROM tbl_user';
$usersStatement = $pdo->prepare($usersQuery . ($query !== '' ? ' WHERE CONCAT(first_name," ",last_name) LIKE ? OR username LIKE ? OR email LIKE ? OR contact_no LIKE ? OR role LIKE ?' : '') . ' ORDER BY created_at DESC');
$like = '%' . $query . '%';
$usersStatement->execute($query !== '' ? [$like, $like, $like, $like, $like] : []);
$users = $usersStatement->fetchAll();
$userForm = $editing || isset($_GET['new']);
?>
<div class="page-heading"><div><p class="eyebrow">PEOPLE WHO KEEP IT MOVING</p><h1>Team & Access</h1><p class="heading-copy">Manage staff accounts and system permissions.</p></div><?php if (!$userForm): ?><a class="button button-primary" href="index.php?page=users&new=1">＋ Add team member</a><?php endif; ?></div>
<?php if ($userForm): ?><section class="panel form-panel"><div class="panel-heading"><div><h2><?= $editing ? 'Edit team member' : 'Create a team account' ?></h2><p>Only administrators can manage user access.</p></div></div>
<form method="post" class="form-grid"><?= csrf_field() ?><input type="hidden" name="action" value="user_save"><input type="hidden" name="user_id" value="<?= (int) ($editing['user_id'] ?? 0) ?>">
    <label>First name <span class="required">*</span><input name="first_name" maxlength="50" required value="<?= e($editing['first_name'] ?? '') ?>"></label><label>Middle name<input name="middle_name" maxlength="50" value="<?= e($editing['middle_name'] ?? '') ?>"></label><label>Last name <span class="required">*</span><input name="last_name" maxlength="50" required value="<?= e($editing['last_name'] ?? '') ?>"></label>
    <label>Sex<select name="sex"><?php foreach (['Male','Female','Other'] as $option): ?><option <?= ($editing['sex'] ?? 'Other') === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select></label><label>Date of birth<input type="date" name="date_of_birth" max="<?= e(date('Y-m-d')) ?>" value="<?= e($editing['date_of_birth'] ?? '') ?>"></label><label>Contact number<input type="tel" name="contact_no" maxlength="20" value="<?= e($editing['contact_no'] ?? '') ?>"></label><label>Email address<input type="email" name="email" maxlength="100" value="<?= e($editing['email'] ?? '') ?>"></label>
    <label>Username <span class="required">*</span><input name="username" maxlength="50" required autocomplete="username" value="<?= e($editing['username'] ?? '') ?>"></label><label>Role<select name="role"><option <?= ($editing['role'] ?? 'Staff') === 'Staff' ? 'selected' : '' ?>>Staff</option><option <?= ($editing['role'] ?? '') === 'Admin' ? 'selected' : '' ?>>Admin</option></select></label>
    <label>Account status<select name="user_status"><option <?= ($editing['user_status'] ?? 'Active') === 'Active' ? 'selected' : '' ?>>Active</option><option <?= ($editing['user_status'] ?? '') === 'Inactive' ? 'selected' : '' ?>>Inactive</option></select></label><label>Password <?= $editing ? '(leave blank to keep current)' : '<span class="required">*</span>' ?><input type="password" name="password" <?= $editing ? '' : 'required' ?> minlength="8" maxlength="72" autocomplete="new-password" placeholder="At least 8 characters"></label>
    <div class="form-actions span-two"><a href="index.php?page=users" class="button button-quiet">Cancel</a><button class="button button-primary"><?= $editing ? 'Save changes' : 'Create account' ?></button></div>
</form></section>
<?php else: ?><section class="panel"><div class="panel-heading"><div><h2>Team Directory <span class="count-pill"><?= count($users) ?></span></h2><p>Search team members by name, username, email, contact, or role.</p></div><form method="get" class="search-form"><input type="hidden" name="page" value="users"><input name="q" value="<?= e($query) ?>" placeholder="Name, username, email, role" aria-label="Search team members"><button class="button button-secondary button-small">Search</button><?php if ($query): ?><a class="button button-quiet button-small" href="index.php?page=users">Clear</a><?php endif; ?></form></div><div class="table-wrap"><table><thead><tr><th>TEAM MEMBER</th><th>USERNAME</th><th>ROLE</th><th>CONTACT</th><th>STATUS</th><th>ADDED</th><th></th></tr></thead><tbody>
<?php if (!$users): ?><tr><td colspan="7"><div class="empty-state"><strong><?= $query ? 'No matching team members' : 'No team accounts' ?></strong><span><?= $query ? 'Try a different name, username, email, contact, or role.' : 'Create accounts for the people who need to access the system.' ?></span></div></td></tr><?php endif; ?>
<?php foreach ($users as $user): ?><tr><td class="person-cell"><span class="person-avatar person-avatar-fallback"><?= e(strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1))) ?></span><span><strong><?= e(full_name($user)) ?></strong><small><?= e($user['email'] ?: 'Team member') ?></small></span></td><td><?= e($user['username']) ?></td><td><span class="role-pill"><?= e($user['role']) ?></span></td><td><?= e($user['contact_no'] ?: '—') ?></td><td><?= render_status($user['user_status']) ?></td><td><?= e(date('M j, Y', strtotime($user['created_at']))) ?></td><td><a class="button button-quiet button-small" href="index.php?page=users&edit=<?= (int) $user['user_id'] ?>">Edit</a></td></tr><?php endforeach; ?>
</tbody></table></div></section><?php endif; ?>
