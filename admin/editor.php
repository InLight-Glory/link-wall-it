<?php
// admin/editor.php — Unified folder-tree editor (UI Overhaul Phase 2).
// Renders the full Building -> Side -> Wall -> Link tree with modals for all CRUD.

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../app/core/functions.php';

require_login();

$error_message = '';
$success_message = '';

// --- Image upload helper (duplicated from manage_wall.php intentionally —
// will be extracted to a shared connector in a future phase).
function editor_handle_image_upload($file_input_name) {
    if (!isset($_FILES[$file_input_name]) || $_FILES[$file_input_name]['error'] !== UPLOAD_ERR_OK) {
        return ['path' => null];
    }
    $file = $_FILES[$file_input_name];
    $max_size = 500 * 1024;
    if ($file['size'] > $max_size) {
        return ['error' => 'Image too large (500KB max).'];
    }
    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, $allowed)) {
        return ['error' => 'Only JPG, PNG, GIF, WEBP images are allowed.'];
    }
    $upload_dir = __DIR__ . '/../assets/images/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    $new_filename = uniqid('', true) . '_' . basename($file['name']);
    $destination = $upload_dir . $new_filename;
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return ['error' => 'Failed to save uploaded image.'];
    }
    return ['path' => 'assets/images/' . $new_filename];
}

// --- POST handler --------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';

    // AJAX-only actions return JSON directly and skip the PRG redirect.
    if ($action === 'reorder_links') {
        // Hard-isolate this endpoint so any PHP notice/warning can't corrupt the JSON
        // response. We capture and log them instead of inlining them in the body.
        @ini_set('display_errors', '0');
        while (ob_get_level() > 0) { ob_end_clean(); }
        ob_start();

        header('Content-Type: application/json');

        $wall_id = $_POST['wall_id'] ?? '';
        $link_ids = $_POST['link_ids'] ?? [];
        if (!is_array($link_ids)) { $link_ids = []; }

        $code = 200;
        $payload = ['ok' => true];

        try {
            if ($wall_id === '' || empty($link_ids)) {
                $code = 400;
                $payload = ['error' => 'wall_id and link_ids required'];
            } elseif (!reorder_links_for_wall($wall_id, $link_ids)) {
                $code = 500;
                $payload = ['error' => 'reorder failed'];
            }
        } catch (Throwable $t) {
            error_log('[editor:reorder_links] ' . $t->getMessage() . ' at ' . $t->getFile() . ':' . $t->getLine());
            $code = 500;
            $payload = ['error' => 'server error'];
        }

        $garbage = ob_get_clean();
        if ($garbage !== '') {
            error_log('[editor:reorder_links] suppressed pre-JSON output: ' . substr($garbage, 0, 500));
        }
        http_response_code($code);
        echo json_encode($payload);
        exit;
    }

    try {
        switch ($action) {
            // --- Buildings ---
            case 'create_building':
                $name = trim($_POST['name'] ?? '');
                if ($name === '') { throw new Exception('Building name is required.'); }
                if (!create_building($name)) { throw new Exception('Failed to create building.'); }
                $success_message = 'Building created.';
                break;

            case 'update_building':
                $id = $_POST['id'] ?? '';
                $name = trim($_POST['name'] ?? '');
                if ($id === '' || $name === '') { throw new Exception('Building name is required.'); }
                if (!update_building($id, $name)) { throw new Exception('Failed to rename building.'); }
                $success_message = 'Building renamed.';
                break;

            case 'delete_building':
                $id = $_POST['id'] ?? '';
                if (!delete_building($id)) { throw new Exception('Failed to delete building.'); }
                $success_message = 'Building deleted.';
                break;

            // --- Sides ---
            case 'create_side':
                $building_id = $_POST['building_id'] ?? '';
                $name = trim($_POST['name'] ?? '');
                if ($building_id === '' || $name === '') { throw new Exception('Side name is required.'); }
                if (!create_side($building_id, $name)) {
                    throw new Exception('Failed to create side. (A building can have at most 4 sides.)');
                }
                $success_message = 'Side created.';
                break;

            case 'update_side':
                $id = $_POST['id'] ?? '';
                $name = trim($_POST['name'] ?? '');
                if ($id === '' || $name === '') { throw new Exception('Side name is required.'); }
                if (!update_side($id, $name)) { throw new Exception('Failed to rename side.'); }
                $success_message = 'Side renamed.';
                break;

            case 'delete_side':
                $id = $_POST['id'] ?? '';
                if (!delete_side($id)) { throw new Exception('Failed to delete side.'); }
                $success_message = 'Side deleted.';
                break;

            // --- Walls ---
            case 'create_wall':
                $side_id = $_POST['side_id'] ?? '';
                $name = trim($_POST['name'] ?? '');
                if ($side_id === '' || $name === '') { throw new Exception('Wall name is required.'); }
                if (!create_wall($side_id, $name)) { throw new Exception('Failed to create wall.'); }
                $success_message = 'Wall created.';
                break;

            case 'update_wall':
                $id = $_POST['id'] ?? '';
                $name = trim($_POST['name'] ?? '');
                if ($id === '' || $name === '') { throw new Exception('Wall name is required.'); }
                if (!update_wall($id, $name)) { throw new Exception('Failed to rename wall.'); }
                $success_message = 'Wall renamed.';
                break;

            case 'delete_wall':
                $id = $_POST['id'] ?? '';
                if (!delete_wall($id)) { throw new Exception('Failed to delete wall.'); }
                $success_message = 'Wall deleted.';
                break;

            case 'update_wall_access':
                $id = $_POST['id'] ?? '';
                $access_type = $_POST['access_type'] ?? 'public';
                $access_value = null;

                if ($access_type === 'password') {
                    $access_value = $_POST['access_password'] ?? '';
                    if ($access_value === '') { $access_type = 'public'; }
                } elseif ($access_type === 'codelist') {
                    $codelist_str = $_POST['access_codelist'] ?? '';
                    $access_value = array_filter(array_map('trim', preg_split('/[,\n\r]+/', $codelist_str)));
                    if (empty($access_value)) { $access_type = 'public'; }
                } elseif ($access_type === 'payment') {
                    $access_value = floatval($_POST['access_price'] ?? 0);
                }

                if (!update_wall_access($id, $access_type, $access_value)) {
                    throw new Exception('Failed to update access.');
                }
                $success_message = 'Access updated.';
                break;

            // --- Links ---
            case 'create_link':
            case 'update_link':
                $wall_id = $_POST['wall_id'] ?? '';
                $link_id = $_POST['id'] ?? '';
                $title = trim($_POST['link_title'] ?? '');
                $url = trim($_POST['link_url'] ?? '');
                $description = $_POST['link_description'] ?? '';

                if ($wall_id === '' || $title === '' || $url === '') {
                    throw new Exception('Title and URL are required.');
                }
                if (!filter_var($url, FILTER_VALIDATE_URL)) {
                    throw new Exception('URL is not valid.');
                }

                $wall_for_link = get_wall($wall_id);
                if (!$wall_for_link) { throw new Exception('Wall not found.'); }

                $is_protected = $wall_for_link['access_control']['type'] === 'password';
                $wall_password = $_POST['wall_password_for_encryption'] ?? '';
                if ($is_protected) {
                    if (!verify_password(
                        $wall_password,
                        $wall_for_link['access_control']['password']['hash'],
                        $wall_for_link['access_control']['password']['salt']
                    )) {
                        throw new Exception('Incorrect wall password.');
                    }
                }

                $image_result = editor_handle_image_upload('link_image');
                if (isset($image_result['error'])) { throw new Exception($image_result['error']); }

                $link_data = [
                    'title' => $is_protected
                        ? encrypt_data($title, $wall_password)
                        : htmlspecialchars($title, ENT_QUOTES, 'UTF-8'),
                    'url' => $url,
                    'description' => $is_protected
                        ? encrypt_data($description, $wall_password)
                        : htmlspecialchars($description, ENT_QUOTES, 'UTF-8'),
                    'image' => $image_result['path'],
                ];

                if ($action === 'create_link') {
                    if (!create_link($wall_id, $link_data)) {
                        throw new Exception('Failed to create link.');
                    }
                    $success_message = 'Link added.';
                } else {
                    // Update — preserve existing image unless replaced or explicitly deleted.
                    $current_link = get_link($link_id);
                    if (!$current_link) { throw new Exception('Link not found.'); }
                    $existing_image = $current_link['image'] ?? null;

                    if (!empty($_POST['delete_image']) && $existing_image) {
                        $disk_path = __DIR__ . '/../' . $existing_image;
                        if (file_exists($disk_path)) { @unlink($disk_path); }
                        $existing_image = null;
                    }

                    if ($image_result['path'] === null) {
                        $link_data['image'] = $existing_image;
                    } else {
                        // New image replaces old — delete the old file.
                        if ($existing_image) {
                            $old_disk = __DIR__ . '/../' . $existing_image;
                            if (file_exists($old_disk)) { @unlink($old_disk); }
                        }
                    }

                    if (!update_link($link_id, $link_data)) {
                        throw new Exception('Failed to update link.');
                    }
                    $success_message = 'Link updated.';
                }
                break;

            case 'delete_link':
                $id = $_POST['id'] ?? '';
                if (!delete_link($id)) { throw new Exception('Failed to delete link.'); }
                $success_message = 'Link deleted.';
                break;

            default:
                throw new Exception('Unknown action.');
        }
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }

    // PRG: redirect on success so refresh doesn't resubmit.
    if ($error_message === '') {
        $flash = urlencode($success_message);
        header('Location: editor.php?ok=' . $flash);
        exit;
    }
}

if (isset($_GET['ok']) && $_GET['ok'] !== '') {
    $success_message = $_GET['ok'];
}

// --- Data fetch (build the tree) ----------------------------------------
$db = get_db();
$buildings = $db['buildings'] ?? [];
$all_sides = $db['sides'] ?? [];
$all_walls = $db['walls'] ?? [];
$all_links = $db['links'] ?? [];

// Index for fast lookup
$sides_by_building = [];
foreach ($all_sides as $s) {
    $sides_by_building[$s['building_id']][] = $s;
}
$walls_by_side = [];
foreach ($all_walls as $w) {
    $walls_by_side[$w['side_id']][] = $w;
}
$links_by_wall = [];
foreach ($all_links as $l) {
    $links_by_wall[$l['wall_id']][] = $l;
}

$csrf = generate_csrf_token();

function access_label($wall) {
    $type = $wall['access_control']['type'] ?? 'public';
    switch ($type) {
        case 'public':          return ['Public', 'tree__badge'];
        case 'password':        return ['Locked', 'tree__badge tree__badge--locked'];
        case 'codelist':        return ['Codes', 'tree__badge tree__badge--locked'];
        case 'payment':         return ['Paid', 'tree__badge tree__badge--paid'];
        case 'email_allowlist': return ['Email', 'tree__badge tree__badge--locked'];
        default:                return [ucfirst($type), 'tree__badge'];
    }
}
?>
<!DOCTYPE html>
<html lang="en"<?= theme_html_attr() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editor &middot; Admin</title>
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf) ?>">
    <link rel="stylesheet" href="../assets/css/app.css">
</head>
<body>
    <div class="container container--wide">
        <header class="app-header">
            <h1>Editor</h1>
            <nav class="app-header__nav">
                <a href="index.php">Buildings</a>
                <a href="settings.php">Settings</a>
                <a href="../index.php">View site</a>
                <a href="logout.php" class="danger">Sign out</a>
            </nav>
        </header>

        <?php if ($success_message): ?>
            <div class="message message--success"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="message message--error"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <div class="tree-toolbar">
            <div>
                <button type="button" class="btn"
                        data-modal="modal-name"
                        data-action="create_building"
                        data-title="Create building"
                        data-label="Building name">
                    New building
                </button>
            </div>
            <div class="tree-toolbar__hint">Hover a node to see its actions.</div>
        </div>

        <div class="tree">
            <?php if (empty($buildings)): ?>
                <div class="tree__empty">
                    No buildings yet. Click <strong>New building</strong> to get started.
                </div>
            <?php else: ?>
                <?php foreach ($buildings as $b): ?>
                    <details data-tree-id="<?= htmlspecialchars($b['id']) ?>">
                        <summary>
                            <div class="tree__row">
                                <span class="tree__chevron">&#x25B6;</span>
                                <span class="tree__icon">B</span>
                                <span class="tree__name"><?= htmlspecialchars($b['name']) ?></span>
                                <span class="tree__actions">
                                    <a class="btn btn--ghost btn--sm" href="../building.php?id=<?= htmlspecialchars($b['id']) ?>" target="_blank" rel="noopener noreferrer" title="Open public page">Share</a>
                                    <button type="button" class="btn btn--ghost btn--sm"
                                            data-modal="modal-name"
                                            data-action="create_side"
                                            data-building-id="<?= htmlspecialchars($b['id']) ?>"
                                            data-title="New side in <?= htmlspecialchars($b['name'], ENT_QUOTES) ?>"
                                            data-label="Side name">Add side</button>
                                    <button type="button" class="btn btn--ghost btn--sm"
                                            data-modal="modal-name"
                                            data-action="update_building"
                                            data-id="<?= htmlspecialchars($b['id']) ?>"
                                            data-name="<?= htmlspecialchars($b['name'], ENT_QUOTES) ?>"
                                            data-title="Rename building"
                                            data-label="Building name">Rename</button>
                                    <form method="post" style="display:inline;" onsubmit="return confirm('Delete this building and all its contents?');">
                                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                        <input type="hidden" name="action" value="delete_building">
                                        <input type="hidden" name="id" value="<?= htmlspecialchars($b['id']) ?>">
                                        <button type="submit" class="btn btn--ghost btn--sm" style="color: var(--color-danger);">Delete</button>
                                    </form>
                                </span>
                            </div>
                        </summary>
                        <div class="tree__children">
                            <?php
                            $b_sides = $sides_by_building[$b['id']] ?? [];
                            if (empty($b_sides)): ?>
                                <div class="tree__children-empty">No sides yet.</div>
                            <?php else: foreach ($b_sides as $s): ?>
                                <details data-tree-id="<?= htmlspecialchars($s['id']) ?>">
                                    <summary>
                                        <div class="tree__row">
                                            <span class="tree__chevron">&#x25B6;</span>
                                            <span class="tree__icon">S</span>
                                            <span class="tree__name"><?= htmlspecialchars($s['name']) ?></span>
                                            <span class="tree__actions">
                                                <a class="btn btn--ghost btn--sm" href="../side.php?id=<?= htmlspecialchars($s['id']) ?>" target="_blank" rel="noopener noreferrer">Share</a>
                                                <button type="button" class="btn btn--ghost btn--sm"
                                                        data-modal="modal-name"
                                                        data-action="create_wall"
                                                        data-side-id="<?= htmlspecialchars($s['id']) ?>"
                                                        data-title="New wall in <?= htmlspecialchars($s['name'], ENT_QUOTES) ?>"
                                                        data-label="Wall name">Add wall</button>
                                                <button type="button" class="btn btn--ghost btn--sm"
                                                        data-modal="modal-name"
                                                        data-action="update_side"
                                                        data-id="<?= htmlspecialchars($s['id']) ?>"
                                                        data-name="<?= htmlspecialchars($s['name'], ENT_QUOTES) ?>"
                                                        data-title="Rename side"
                                                        data-label="Side name">Rename</button>
                                                <form method="post" style="display:inline;" onsubmit="return confirm('Delete this side and all its contents?');">
                                                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                                    <input type="hidden" name="action" value="delete_side">
                                                    <input type="hidden" name="id" value="<?= htmlspecialchars($s['id']) ?>">
                                                    <button type="submit" class="btn btn--ghost btn--sm" style="color: var(--color-danger);">Delete</button>
                                                </form>
                                            </span>
                                        </div>
                                    </summary>
                                    <div class="tree__children">
                                        <?php
                                        $s_walls = $walls_by_side[$s['id']] ?? [];
                                        if (empty($s_walls)): ?>
                                            <div class="tree__children-empty">No walls yet.</div>
                                        <?php else: foreach ($s_walls as $w):
                                            [$badge_text, $badge_class] = access_label($w);
                                            $is_protected = ($w['access_control']['type'] ?? 'public') === 'password';
                                            $price = $w['access_control']['payment']['price'] ?? 0;
                                        ?>
                                            <details data-tree-id="<?= htmlspecialchars($w['id']) ?>">
                                                <summary>
                                                    <div class="tree__row">
                                                        <span class="tree__chevron">&#x25B6;</span>
                                                        <span class="tree__icon">W</span>
                                                        <span class="tree__name"><?= htmlspecialchars($w['name']) ?></span>
                                                        <span class="<?= $badge_class ?>" title="Access type"><?= htmlspecialchars($badge_text) ?></span>
                                                        <span class="tree__actions">
                                                            <a class="btn btn--ghost btn--sm" href="<?= htmlspecialchars(wall_short_url($w)) ?>" target="_blank" rel="noopener noreferrer" title="Open share link">Share</a>
                                                            <button type="button" class="btn btn--ghost btn--sm"
                                                                    data-modal="modal-link"
                                                                    data-mode="create"
                                                                    data-wall-id="<?= htmlspecialchars($w['id']) ?>"
                                                                    data-wall-protected="<?= $is_protected ? '1' : '0' ?>">Add link</button>
                                                            <button type="button" class="btn btn--ghost btn--sm"
                                                                    data-modal="modal-access"
                                                                    data-id="<?= htmlspecialchars($w['id']) ?>"
                                                                    data-access-type="<?= htmlspecialchars($w['access_control']['type'] ?? 'public') ?>"
                                                                    data-price="<?= htmlspecialchars((string)$price) ?>">Access</button>
                                                            <a class="btn btn--ghost btn--sm" href="wall_settings.php?wall_id=<?= htmlspecialchars($w['id']) ?>">Settings</a>
                                                            <button type="button" class="btn btn--ghost btn--sm"
                                                                    data-modal="modal-name"
                                                                    data-action="update_wall"
                                                                    data-id="<?= htmlspecialchars($w['id']) ?>"
                                                                    data-name="<?= htmlspecialchars($w['name'], ENT_QUOTES) ?>"
                                                                    data-title="Rename wall"
                                                                    data-label="Wall name">Rename</button>
                                                            <form method="post" style="display:inline;" onsubmit="return confirm('Delete this wall and all its links?');">
                                                                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                                                <input type="hidden" name="action" value="delete_wall">
                                                                <input type="hidden" name="id" value="<?= htmlspecialchars($w['id']) ?>">
                                                                <button type="submit" class="btn btn--ghost btn--sm" style="color: var(--color-danger);">Delete</button>
                                                            </form>
                                                        </span>
                                                    </div>
                                                </summary>
                                                <div class="tree__children">
                                                    <?php
                                                    $w_links = $links_by_wall[$w['id']] ?? [];
                                                    if (empty($w_links)): ?>
                                                        <div class="tree__children-empty">No links yet.</div>
                                                    <?php else: foreach ($w_links as $l):
                                                        // Display title: if wall is password-protected, the title is encrypted —
                                                        // show a placeholder rather than ciphertext.
                                                        $display_title = $is_protected ? '[encrypted]' : $l['title'];
                                                    ?>
                                                        <div class="tree__link-row" draggable="true"
                                                             data-link-id="<?= htmlspecialchars($l['id']) ?>"
                                                             data-wall-id="<?= htmlspecialchars($w['id']) ?>">
                                                            <span class="tree__drag-handle" title="Drag to reorder">&#x2630;</span>
                                                            <span class="tree__chevron tree__chevron--leaf">&middot;</span>
                                                            <span class="tree__icon">L</span>
                                                            <span class="tree__name tree__name--link"><?= htmlspecialchars($display_title) ?></span>
                                                            <span class="tree__url"><?= htmlspecialchars($l['url']) ?></span>
                                                            <span class="tree__actions">
                                                                <a class="btn btn--ghost btn--sm" href="<?= htmlspecialchars($l['url']) ?>" target="_blank" rel="noopener noreferrer">Open</a>
                                                                <button type="button" class="btn btn--ghost btn--sm"
                                                                        data-modal="modal-link"
                                                                        data-mode="edit"
                                                                        data-wall-id="<?= htmlspecialchars($w['id']) ?>"
                                                                        data-wall-protected="<?= $is_protected ? '1' : '0' ?>"
                                                                        data-id="<?= htmlspecialchars($l['id']) ?>"
                                                                        data-link-title="<?= htmlspecialchars($display_title, ENT_QUOTES) ?>"
                                                                        data-link-url="<?= htmlspecialchars($l['url'], ENT_QUOTES) ?>"
                                                                        data-link-description="<?= htmlspecialchars($is_protected ? '' : $l['description'], ENT_QUOTES) ?>"
                                                                        data-link-image="<?= htmlspecialchars($l['image'] ?? '', ENT_QUOTES) ?>">Edit</button>
                                                                <form method="post" style="display:inline;" onsubmit="return confirm('Delete this link?');">
                                                                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                                                    <input type="hidden" name="action" value="delete_link">
                                                                    <input type="hidden" name="id" value="<?= htmlspecialchars($l['id']) ?>">
                                                                    <button type="submit" class="btn btn--ghost btn--sm" style="color: var(--color-danger);">Delete</button>
                                                                </form>
                                                            </span>
                                                        </div>
                                                    <?php endforeach; endif; ?>
                                                </div>
                                            </details>
                                        <?php endforeach; endif; ?>
                                    </div>
                                </details>
                            <?php endforeach; endif; ?>
                        </div>
                    </details>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- Modal: Name (create/rename Building/Side/Wall) -->
    <!-- ============================================================ -->
    <div class="modal-backdrop" id="modal-name" data-modal-root>
        <form class="modal" method="post" data-modal-form>
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="action" data-field="action">
            <input type="hidden" name="id" data-field="id">
            <input type="hidden" name="building_id" data-field="building_id">
            <input type="hidden" name="side_id" data-field="side_id">

            <div class="modal__header">
                <span class="modal__title" data-field="title">Rename</span>
                <button type="button" class="modal__close" data-modal-close aria-label="Close">&times;</button>
            </div>
            <div class="modal__body">
                <div class="field">
                    <label data-field="label">Name</label>
                    <input type="text" name="name" data-field="name" required autofocus>
                </div>
            </div>
            <div class="modal__footer">
                <button type="button" class="btn btn--ghost" data-modal-close>Cancel</button>
                <button type="submit" class="btn">Save</button>
            </div>
        </form>
    </div>

    <!-- ============================================================ -->
    <!-- Modal: Link (create/edit) -->
    <!-- ============================================================ -->
    <div class="modal-backdrop" id="modal-link" data-modal-root>
        <form class="modal modal--wide" method="post" enctype="multipart/form-data" data-modal-form>
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="action" data-field="action">
            <input type="hidden" name="id" data-field="id">
            <input type="hidden" name="wall_id" data-field="wall_id">

            <div class="modal__header">
                <span class="modal__title" data-field="title">Add link</span>
                <button type="button" class="modal__close" data-modal-close aria-label="Close">&times;</button>
            </div>
            <div class="modal__body">
                <div data-field="protected_warn" style="display: none;">
                    <div class="message message--warn">
                        This wall is password-protected. Enter the wall password to encrypt this link.
                    </div>
                    <div class="field">
                        <label>Wall password</label>
                        <input type="password" name="wall_password_for_encryption" data-field="wall_password">
                    </div>
                </div>

                <div class="field">
                    <label>Title</label>
                    <input type="text" name="link_title" data-field="link_title" required>
                </div>
                <div class="field">
                    <label>URL</label>
                    <input type="url" name="link_url" placeholder="https://example.com" data-field="link_url" required>
                </div>
                <div class="field">
                    <label>Description</label>
                    <textarea name="link_description" rows="2" data-field="link_description"></textarea>
                </div>
                <div class="field">
                    <label>Image (max 500KB)</label>
                    <input type="file" name="link_image" accept="image/*">
                </div>
                <div class="field" data-field="current_image_block" style="display: none;">
                    <label>Current image</label>
                    <img data-field="current_image_preview" alt="" style="width: 56px; height: 56px; object-fit: cover; border-radius: var(--radius-sm); display: block; margin-bottom: var(--space-2);">
                    <label style="font-weight: 400; color: var(--color-text-muted); font-size: var(--text-sm);">
                        <input type="checkbox" name="delete_image" value="1" style="width: auto; margin-right: var(--space-1);"> Remove current image
                    </label>
                </div>
            </div>
            <div class="modal__footer">
                <button type="button" class="btn btn--ghost" data-modal-close>Cancel</button>
                <button type="submit" class="btn">Save</button>
            </div>
        </form>
    </div>

    <!-- ============================================================ -->
    <!-- Modal: Access control (tabs: Public / Password / Codelist / Pay) -->
    <!-- ============================================================ -->
    <div class="modal-backdrop" id="modal-access" data-modal-root>
        <form class="modal modal--wide" method="post" data-modal-form>
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="update_wall_access">
            <input type="hidden" name="id" data-field="id">
            <input type="hidden" name="access_type" data-field="access_type">

            <div class="modal__header">
                <span class="modal__title">Wall access</span>
                <button type="button" class="modal__close" data-modal-close aria-label="Close">&times;</button>
            </div>
            <div class="modal__body">
                <div class="tabs" role="tablist">
                    <button type="button" class="tab" data-tab="public">Public</button>
                    <button type="button" class="tab" data-tab="password">Password</button>
                    <button type="button" class="tab" data-tab="codelist">Codelist</button>
                    <button type="button" class="tab" data-tab="email_allowlist">Email&nbsp;allowlist</button>
                    <button type="button" class="tab" data-tab="payment">Payment</button>
                </div>

                <div class="tab-panel" data-tab-panel="public">
                    <p style="color: var(--color-text-muted); font-size: var(--text-sm); margin: 0;">
                        Anyone with the link can view this wall.
                    </p>
                </div>

                <div class="tab-panel" data-tab-panel="password">
                    <p style="color: var(--color-text-muted); font-size: var(--text-sm);">
                        Visitors must enter a single password. Link titles and descriptions are encrypted at rest with this password.
                    </p>
                    <div class="field">
                        <label>Password</label>
                        <input type="password" name="access_password" placeholder="New password (leave blank to keep)">
                    </div>
                </div>

                <div class="tab-panel" data-tab-panel="codelist">
                    <p style="color: var(--color-text-muted); font-size: var(--text-sm);">
                        Provide multiple codes — visitors enter any one of them. Comma- or newline-separated.
                    </p>
                    <div class="field">
                        <label>Codes</label>
                        <textarea name="access_codelist" rows="4" placeholder="code1, code2, code3"></textarea>
                    </div>
                </div>

                <div class="tab-panel" data-tab-panel="email_allowlist">
                    <p style="color: var(--color-text-muted); font-size: var(--text-sm);">
                        Visitors enter their email at the wall to verify access. Their email must
                        already be on the allowlist (managed on the wall's Settings page).
                    </p>
                    <p style="font-size: var(--text-sm);">
                        <a data-field="settings_link" href="wall_settings.php">Manage allowlist for this wall &rarr;</a>
                    </p>
                </div>

                <div class="tab-panel" data-tab-panel="payment">
                    <p style="color: var(--color-text-muted); font-size: var(--text-sm);">
                        Charge a one-time fee via Stripe. Configure your Stripe keys in <a href="settings.php">Settings</a> first.
                    </p>
                    <div class="message message--warn" style="font-size: var(--text-xs);">
                        Frontend payment enforcement is not yet implemented (tracked as LWI-002). Walls set to Payment currently behave as Public on the public site.
                    </div>
                    <div class="field">
                        <label>Price (USD)</label>
                        <input type="number" name="access_price" step="0.01" min="0" placeholder="5.00" data-field="access_price">
                    </div>
                </div>
            </div>
            <div class="modal__footer">
                <button type="button" class="btn btn--ghost" data-modal-close>Cancel</button>
                <button type="submit" class="btn">Save access</button>
            </div>
        </form>
    </div>

    <script>
        // ---- Tree expansion persistence ----
        (function() {
            const STORAGE_KEY = 'lwi_editor_open';
            const open = new Set(JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]'));

            document.querySelectorAll('details[data-tree-id]').forEach(d => {
                const id = d.dataset.treeId;
                if (open.has(id)) d.open = true;
                d.addEventListener('toggle', () => {
                    if (d.open) open.add(id); else open.delete(id);
                    localStorage.setItem(STORAGE_KEY, JSON.stringify([...open]));
                });
            });

            // Stop summary clicks on action buttons from toggling the details.
            document.querySelectorAll('.tree__actions').forEach(el => {
                el.addEventListener('click', e => e.stopPropagation());
            });
        })();

        // ---- Modal helpers ----
        const modals = {
            open(id, populate) {
                const root = document.getElementById(id);
                if (!root) return;
                // Reset form
                const form = root.querySelector('form');
                if (form) form.reset();
                // Populate
                if (typeof populate === 'function') populate(root);
                root.classList.add('is-open');
                // Focus first input
                const firstInput = root.querySelector('input:not([type=hidden]), textarea, select');
                if (firstInput) setTimeout(() => firstInput.focus(), 30);
            },
            close(root) {
                root.classList.remove('is-open');
            }
        };

        // Close on backdrop click or close button
        document.querySelectorAll('[data-modal-root]').forEach(root => {
            root.addEventListener('click', e => {
                if (e.target === root) modals.close(root);
            });
            root.querySelectorAll('[data-modal-close]').forEach(b => {
                b.addEventListener('click', () => modals.close(root));
            });
        });

        // Close on Escape
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal-backdrop.is-open').forEach(modals.close);
            }
        });

        // ---- Trigger wiring ----
        document.querySelectorAll('[data-modal]').forEach(btn => {
            btn.addEventListener('click', e => {
                const modalId = btn.dataset.modal;

                if (modalId === 'modal-name') {
                    modals.open(modalId, root => {
                        const set = (key, val) => {
                            const el = root.querySelector(`[data-field="${key}"]`);
                            if (!el) return;
                            if (el.tagName === 'INPUT' || el.tagName === 'SELECT' || el.tagName === 'TEXTAREA') {
                                el.value = val ?? '';
                            } else {
                                el.textContent = val ?? '';
                            }
                        };
                        set('action', btn.dataset.action || '');
                        set('id', btn.dataset.id || '');
                        set('building_id', btn.dataset.buildingId || '');
                        set('side_id', btn.dataset.sideId || '');
                        set('name', btn.dataset.name || '');
                        set('title', btn.dataset.title || 'Edit');
                        set('label', btn.dataset.label || 'Name');
                    });
                }

                else if (modalId === 'modal-link') {
                    modals.open(modalId, root => {
                        const mode = btn.dataset.mode; // 'create' or 'edit'
                        const isProtected = btn.dataset.wallProtected === '1';
                        root.querySelector('[data-field="action"]').value = mode === 'edit' ? 'update_link' : 'create_link';
                        root.querySelector('[data-field="title"]').textContent = mode === 'edit' ? 'Edit link' : 'Add link';
                        root.querySelector('[data-field="id"]').value = btn.dataset.id || '';
                        root.querySelector('[data-field="wall_id"]').value = btn.dataset.wallId || '';
                        root.querySelector('[data-field="link_title"]').value = btn.dataset.linkTitle || '';
                        root.querySelector('[data-field="link_url"]').value = btn.dataset.linkUrl || '';
                        root.querySelector('[data-field="link_description"]').value = btn.dataset.linkDescription || '';

                        // Protected wall warning
                        const warn = root.querySelector('[data-field="protected_warn"]');
                        const pwField = root.querySelector('[data-field="wall_password"]');
                        if (warn) warn.style.display = isProtected ? 'block' : 'none';
                        if (pwField) pwField.required = isProtected;

                        // Current image preview (edit mode only)
                        const imageBlock = root.querySelector('[data-field="current_image_block"]');
                        const imagePreview = root.querySelector('[data-field="current_image_preview"]');
                        if (mode === 'edit' && btn.dataset.linkImage) {
                            imagePreview.src = '../' + btn.dataset.linkImage;
                            imageBlock.style.display = 'block';
                        } else {
                            imageBlock.style.display = 'none';
                        }
                    });
                }

                else if (modalId === 'modal-access') {
                    modals.open(modalId, root => {
                        const wallId = btn.dataset.id || '';
                        const currentType = btn.dataset.accessType || 'public';
                        const price = btn.dataset.price || '';
                        root.querySelector('[data-field="id"]').value = wallId;
                        const accessPriceEl = root.querySelector('[data-field="access_price"]');
                        if (accessPriceEl) accessPriceEl.value = price;
                        const settingsLink = root.querySelector('[data-field="settings_link"]');
                        if (settingsLink) settingsLink.href = 'wall_settings.php?wall_id=' + encodeURIComponent(wallId);
                        activateTab(root, currentType);
                    });
                }
            });
        });

        // ---- Tab switching inside the access modal ----
        function activateTab(root, name) {
            root.querySelectorAll('.tab').forEach(t => {
                t.classList.toggle('is-active', t.dataset.tab === name);
            });
            root.querySelectorAll('[data-tab-panel]').forEach(p => {
                const isActive = p.dataset.tabPanel === name;
                p.classList.toggle('is-active', isActive);
                // Disable inputs in inactive panels so their values don't block form
                // validation (e.g., a hidden "price" field can't trigger min-value errors).
                p.querySelectorAll('input, textarea, select').forEach(el => {
                    el.disabled = !isActive;
                });
            });
            const hidden = root.querySelector('[data-field="access_type"]');
            if (hidden) hidden.value = name;
        }
        document.querySelectorAll('#modal-access .tab').forEach(t => {
            t.addEventListener('click', () => activateTab(document.getElementById('modal-access'), t.dataset.tab));
        });

        // ---- Drag-to-reorder links within a wall ----
        (function() {
            const CSRF = document.querySelector('meta[name="csrf-token"]').content;
            let dragSrc = null;

            function clearMarkers(container) {
                container.querySelectorAll('.tree__link-row').forEach(r => {
                    r.classList.remove('drop-above', 'drop-below');
                });
            }

            function postReorder(wallId, orderedIds) {
                const fd = new FormData();
                fd.append('csrf_token', CSRF);
                fd.append('action', 'reorder_links');
                fd.append('wall_id', wallId);
                orderedIds.forEach(id => fd.append('link_ids[]', id));
                return fetch('editor.php', { method: 'POST', body: fd })
                    .then(r => r.ok ? r.json() : Promise.reject(r))
                    .catch(err => {
                        console.error('Reorder failed', err);
                        alert('Could not save the new order. Refresh and try again.');
                    });
            }

            document.querySelectorAll('.tree__link-row[draggable="true"]').forEach(row => {
                row.addEventListener('dragstart', e => {
                    dragSrc = row;
                    row.classList.add('is-dragging');
                    e.dataTransfer.effectAllowed = 'move';
                    e.dataTransfer.setData('text/plain', row.dataset.linkId);
                });
                row.addEventListener('dragend', () => {
                    row.classList.remove('is-dragging');
                    if (row.parentNode) clearMarkers(row.parentNode);
                    dragSrc = null;
                });
                row.addEventListener('dragover', e => {
                    if (!dragSrc) return;
                    // Same-wall only — don't allow cross-wall reordering.
                    if (dragSrc.dataset.wallId !== row.dataset.wallId) return;
                    e.preventDefault();
                    e.dataTransfer.dropEffect = 'move';
                    if (row === dragSrc) return;
                    const rect = row.getBoundingClientRect();
                    const mid = rect.top + rect.height / 2;
                    const above = e.clientY < mid;
                    row.classList.toggle('drop-above', above);
                    row.classList.toggle('drop-below', !above);
                });
                row.addEventListener('dragleave', () => {
                    row.classList.remove('drop-above', 'drop-below');
                });
                row.addEventListener('drop', e => {
                    if (!dragSrc || dragSrc === row) return;
                    if (dragSrc.dataset.wallId !== row.dataset.wallId) return;
                    e.preventDefault();
                    const rect = row.getBoundingClientRect();
                    const mid = rect.top + rect.height / 2;
                    if (e.clientY < mid) {
                        row.parentNode.insertBefore(dragSrc, row);
                    } else {
                        row.parentNode.insertBefore(dragSrc, row.nextSibling);
                    }
                    clearMarkers(row.parentNode);

                    const wallId = row.dataset.wallId;
                    const orderedIds = [...row.parentNode.querySelectorAll('.tree__link-row')]
                        .filter(r => r.dataset.wallId === wallId)
                        .map(r => r.dataset.linkId);
                    postReorder(wallId, orderedIds);
                });
            });
        })();
    </script>
    <?= theme_picker_html() ?>
</body>
</html>
