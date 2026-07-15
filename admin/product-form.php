<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/utility.php';
$pageTitle = 'Add Product';

$uploadDir = dirname(__DIR__) . '/uploads/products';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

function product_slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/i', '-', $text);
    return trim($text, '-') ?: 'product';
}

function product_unique_slug(PDO $pdo, string $base, int $ignoreId = 0): string
{
    $slug = $base;
    $i = 1;
    while (true) {
        $stmt = $pdo->prepare('SELECT id FROM products WHERE slug = ? AND id <> ? LIMIT 1');
        $stmt->execute([$slug, $ignoreId]);
        if (!$stmt->fetchColumn()) {
            return $slug;
        }
        $slug = $base . '-' . $i;
        $i++;
    }
}

function product_save_upload(array $file, string $uploadDir, string $prefix): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }
    if (($file['size'] ?? 0) > 2 * 1024 * 1024) {
        return null;
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];
    if (!isset($allowed[$mime])) {
        return null;
    }
    $name = $prefix . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(3)) . '.' . $allowed[$mime];
    $dest = rtrim($uploadDir, '/\\') . DIRECTORY_SEPARATOR . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return null;
    }
    return 'uploads/products/' . $name;
}

$categories = $pdo->query("SELECT id, name FROM product_categories WHERE status='active' ORDER BY name")->fetchAll();
$subcategories = $pdo->query("SELECT id, category_id, name FROM product_subcategories WHERE status='active' ORDER BY name")->fetchAll();
$sizes = $pdo->query("SELECT id, name FROM product_sizes WHERE status='active' ORDER BY sort_order, name")->fetchAll();
$colors = $pdo->query("SELECT id, name, hex_code FROM product_colors WHERE status='active' ORDER BY name")->fetchAll();

$errors = [];
$edit = null;
$gallery = [];

if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->execute([(int) $_GET['edit']]);
    $edit = $stmt->fetch();
    if ($edit) {
        $pageTitle = 'Edit Product';
        $g = $pdo->prepare('SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order, id');
        $g->execute([(int) $edit['id']]);
        $gallery = $g->fetchAll();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    $skuMode = ($_POST['sku_mode'] ?? 'auto') === 'manual' ? 'manual' : 'auto';
    $name = trim($_POST['name'] ?? '');
    $slugInput = trim($_POST['slug'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $sku = trim($_POST['sku'] ?? '');
    $categoryId = (int) ($_POST['category_id'] ?? 0) ?: null;
    $subcategoryId = (int) ($_POST['subcategory_id'] ?? 0) ?: null;
    $sizeId = (int) ($_POST['size_id'] ?? 0) ?: null;
    $colorId = (int) ($_POST['color_id'] ?? 0) ?: null;
    $status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';
    $price = (float) ($_POST['price'] ?? 0);
    $mrp = (float) ($_POST['mrp'] ?? 0);
    $discount = 0.0;
    if ($mrp > 0 && $price >= 0 && $price <= $mrp) {
        $discount = round((($mrp - $price) / $mrp) * 100, 2);
    } else {
        $discount = (float) ($_POST['discount_percent'] ?? 0);
    }
    $offerFlash = trim($_POST['offer_flash_text'] ?? '');
    $offerCountdown = trim($_POST['offer_countdown'] ?? '');
    $offerBank = trim($_POST['offer_bank_text'] ?? '');
    $stockQty = (int) ($_POST['stock_qty'] ?? 0);
    $metaTitle = trim($_POST['meta_title'] ?? '');
    $metaDescription = trim($_POST['meta_description'] ?? '');
    $weight = (float) ($_POST['weight'] ?? 0);
    $length = (float) ($_POST['length'] ?? 0);
    $width = (float) ($_POST['width'] ?? 0);
    $height = (float) ($_POST['height'] ?? 0);

    $wordCount = preg_match_all('/\S+/u', $name) ?: 0;
    if ($name === '' || $wordCount < 2) {
        $errors[] = 'Product title me kam se kam 2 shabd hone chahiye.';
    }
    if ($price < 0) $errors[] = 'Price cannot be negative.';
    if ($stockQty < 0) $errors[] = 'Stock cannot be negative.';
    if ($skuMode === 'manual' && $sku === '') {
        $errors[] = 'Manual SKU mode me SKU required hai.';
    }

    $slugBase = product_slugify($slugInput !== '' ? $slugInput : $name);
    $slug = product_unique_slug($pdo, $slugBase, $id);

    $currentThumb = null;
    if ($id > 0) {
        $cur = $pdo->prepare('SELECT thumbnail, sku FROM products WHERE id = ?');
        $cur->execute([$id]);
        $row = $cur->fetch();
        $currentThumb = $row['thumbnail'] ?? null;
        if ($skuMode === 'auto' && $sku === '') {
            $sku = (string)($row['sku'] ?? '');
        }
    }

    $thumbPath = product_save_upload($_FILES['thumbnail'] ?? [], $uploadDir, 'thumb');
    if ($thumbPath && $currentThumb) {
        $full = dirname(__DIR__) . '/' . ltrim(str_replace('\\', '/', $currentThumb), '/');
        if (is_file($full)) @unlink($full);
    }
    if (!$thumbPath) {
        $thumbPath = $currentThumb;
    }

    if (!$errors) {
        try {
            if ($skuMode === 'auto' && $sku === '') {
                $catCode = 'GEN';
                if ($categoryId) {
                    $c = $pdo->prepare('SELECT name FROM product_categories WHERE id = ?');
                    $c->execute([$categoryId]);
                    $cn = (string)$c->fetchColumn();
                    $catCode = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $cn), 0, 3) ?: 'GEN');
                }
                $sku = $catCode . '-' . strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $slug), 0, 8)) . '-' . random_int(100, 999);
            }

            if ($id > 0) {
                $pdo->prepare('
                    UPDATE products SET
                        name=?, slug=?, sku=?, sku_mode=?, category_id=?, subcategory_id=?, size_id=?, color_id=?,
                        price=?, mrp=?, discount_percent=?, offer_flash_text=?, offer_countdown=?, offer_bank_text=?,
                        stock_qty=?, description=?, thumbnail=?,
                        meta_title=?, meta_description=?, weight=?, length=?, width=?, height=?, status=?
                    WHERE id=?
                ')->execute([
                    $name, $slug, $sku ?: null, $skuMode, $categoryId, $subcategoryId, $sizeId, $colorId,
                    $price, $mrp, $discount, $offerFlash ?: null, $offerCountdown ?: null, $offerBank ?: null,
                    $stockQty, $description ?: null, $thumbPath,
                    $metaTitle ?: null, $metaDescription ?: null, $weight, $length, $width, $height, $status, $id
                ]);
                $productId = $id;
                log_activity('product_edit', "Updated product #$id");
                flash('success', 'Product updated.');
            } else {
                $pdo->prepare('
                    INSERT INTO products (
                        name, slug, sku, sku_mode, category_id, subcategory_id, size_id, color_id,
                        price, mrp, discount_percent, offer_flash_text, offer_countdown, offer_bank_text,
                        stock_qty, description, thumbnail,
                        meta_title, meta_description, weight, length, width, height, status
                    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                ')->execute([
                    $name, $slug, $sku ?: null, $skuMode, $categoryId, $subcategoryId, $sizeId, $colorId,
                    $price, $mrp, $discount, $offerFlash ?: null, $offerCountdown ?: null, $offerBank ?: null,
                    $stockQty, $description ?: null, $thumbPath,
                    $metaTitle ?: null, $metaDescription ?: null, $weight, $length, $width, $height, $status
                ]);
                $productId = (int)$pdo->lastInsertId();
                log_activity('product_add', "Added product $name");
                flash('success', 'Product added.');
            }

            if (!empty($_FILES['gallery']['name']) && is_array($_FILES['gallery']['name'])) {
                $sort = 0;
                foreach ($_FILES['gallery']['name'] as $i => $gName) {
                    if ($gName === '') continue;
                    $file = [
                        'name' => $_FILES['gallery']['name'][$i],
                        'type' => $_FILES['gallery']['type'][$i],
                        'tmp_name' => $_FILES['gallery']['tmp_name'][$i],
                        'error' => $_FILES['gallery']['error'][$i],
                        'size' => $_FILES['gallery']['size'][$i],
                    ];
                    $path = product_save_upload($file, $uploadDir, 'gal');
                    if ($path) {
                        $pdo->prepare('INSERT INTO product_images (product_id, image_path, sort_order) VALUES (?,?,?)')
                            ->execute([$productId, $path, $sort++]);
                    }
                }
            }

            if (!empty($_POST['remove_gallery']) && is_array($_POST['remove_gallery'])) {
                foreach ($_POST['remove_gallery'] as $gid) {
                    $gid = (int)$gid;
                    $img = $pdo->prepare('SELECT image_path FROM product_images WHERE id=? AND product_id=?');
                    $img->execute([$gid, $productId]);
                    $p = $img->fetchColumn();
                    if ($p) {
                        $full = dirname(__DIR__) . '/' . ltrim(str_replace('\\', '/', (string)$p), '/');
                        if (is_file($full)) @unlink($full);
                        $pdo->prepare('DELETE FROM product_images WHERE id=? AND product_id=?')->execute([$gid, $productId]);
                    }
                }
            }

            header('Location: product-details.php');
            exit;
        } catch (PDOException $e) {
            $errors[] = 'Save failed: SKU/slug already exists or DB error.';
        }
    }

    // Repopulate edit-like array for form
    $edit = [
        'id' => $id,
        'name' => $name,
        'slug' => $slug,
        'sku' => $sku,
        'sku_mode' => $skuMode,
        'category_id' => $categoryId,
        'subcategory_id' => $subcategoryId,
        'size_id' => $sizeId,
        'color_id' => $colorId,
        'price' => $price,
        'mrp' => $mrp,
        'discount_percent' => $discount,
        'offer_flash_text' => $offerFlash,
        'offer_countdown' => $offerCountdown,
        'offer_bank_text' => $offerBank,
        'stock_qty' => $stockQty,
        'description' => $description,
        'thumbnail' => $thumbPath,
        'meta_title' => $metaTitle,
        'meta_description' => $metaDescription,
        'weight' => $weight,
        'length' => $length,
        'width' => $width,
        'height' => $height,
        'status' => $status,
    ];
}

$v = function (string $key, $default = '') use ($edit) {
    return $edit[$key] ?? $default;
};

$selectedCategory = (int)$v('category_id', 0);
$selectedSub = (int)$v('subcategory_id', 0);
$skuMode = $v('sku_mode', 'auto');
$descHtml = (string)$v('description', '');

require_once __DIR__ . '/../includes/header.php';
?>

<div class="pf-wrap">
    <aside class="pf-nav" id="pfNav">
        <div class="pf-nav-label">Product Form</div>
        <button type="button" class="pf-nav-item is-active" data-pf-step="1">
            <span class="pf-nav-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/></svg></span>
            <span>
                <strong>Add Product Details</strong>
                <small>Add Product name &amp; details</small>
            </span>
        </button>
        <button type="button" class="pf-nav-item" data-pf-step="2">
            <span class="pf-nav-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg></span>
            <span>
                <strong>Product gallery</strong>
                <small>thumbnail &amp; Add Gallery</small>
            </span>
        </button>
        <button type="button" class="pf-nav-item" data-pf-step="3">
            <span class="pf-nav-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg></span>
            <span>
                <strong>Product Categories</strong>
                <small>category &amp; listing status</small>
            </span>
        </button>
        <button type="button" class="pf-nav-item" data-pf-step="4">
            <span class="pf-nav-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg></span>
            <span>
                <strong>Selling prices</strong>
                <small>basic price &amp; discount</small>
            </span>
        </button>
        <button type="button" class="pf-nav-item" data-pf-step="5">
            <span class="pf-nav-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14.7 6.3a1 1 0 000 1.4l1.6 1.6a1 1 0 001.4 0l3.77-3.77a6 6 0 01-7.94 7.94l-6.91 6.91a2.12 2.12 0 01-3-3l6.91-6.91a6 6 0 017.94-7.94l-3.76 3.76z"/></svg></span>
            <span>
                <strong>Advance</strong>
                <small>Meta details &amp; Inventory</small>
            </span>
        </button>
        <button type="button" class="pf-nav-item" data-pf-step="6">
            <span class="pf-nav-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg></span>
            <span>
                <strong>Shipping</strong>
                <small>weight &amp; dimensions</small>
            </span>
        </button>
    </aside>

    <div class="pf-main">
        <?php if ($errors): ?><div class="alert alert-error"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>

        <form method="post" enctype="multipart/form-data" id="pfForm" class="pf-form">
            <input type="hidden" name="id" value="<?= (int)$v('id', 0) ?>">
            <input type="hidden" name="slug" id="pfSlugValue" value="<?= e((string)$v('slug', '')) ?>">
            <input type="hidden" name="description" id="pfDescValue" value="<?= e($descHtml) ?>">

            <!-- Step 1 -->
            <section class="pf-step is-active" data-step="1">
                <div class="pf-card">
                    <div class="pf-card-title">SKU (Stock Keeping Unit)</div>
                    <div class="pf-seg" id="pfSkuMode">
                        <label class="pf-seg-btn <?= $skuMode !== 'manual' ? 'is-on' : '' ?>">
                            <input type="radio" name="sku_mode" value="auto" <?= $skuMode !== 'manual' ? 'checked' : '' ?>> Auto-generate on save
                        </label>
                        <label class="pf-seg-btn <?= $skuMode === 'manual' ? 'is-on' : '' ?>">
                            <input type="radio" name="sku_mode" value="manual" <?= $skuMode === 'manual' ? 'checked' : '' ?>> Manual Input
                        </label>
                    </div>
                    <p class="pf-help">Khali chhodoge to save par system SKU banayega (category + product name se).</p>
                    <div class="form-group pf-sku-manual" id="pfSkuManual" <?= $skuMode === 'manual' ? '' : 'hidden' ?>>
                        <label>Manual SKU</label>
                        <input type="text" name="sku" id="pfSkuInput" value="<?= e((string)$v('sku', '')) ?>" placeholder="e.g. GEN-SHIRT-101">
                    </div>
                </div>

                <div class="pf-card">
                    <div class="pf-card-head">
                        <span class="pf-card-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/></svg></span>
                        <h2>Core Product Details</h2>
                    </div>

                    <div class="form-group">
                        <label>Product Title *</label>
                        <input type="text" name="name" id="pfTitle" value="<?= e((string)$v('name', '')) ?>" placeholder="Product title" required>
                        <p class="pf-help">Kam se kam 2 shabd (sirf ek letter ya ek shabd se Next / Submit nahi hoga).</p>
                    </div>

                    <div class="form-group">
                        <label>Product Permalink / Slug
                            <svg class="pf-lock-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                        </label>
                        <input type="text" id="pfSlugPreview" value="<?= e((string)$v('slug', '') ?: 'auto-generated-slug-path') ?>" readonly class="pf-readonly">
                        <p class="pf-help pf-help-info">Automatically synchronized from title. Cannot be manually overridden.</p>
                    </div>

                    <div class="form-group">
                        <label>Description</label>
                        <div class="pf-editor">
                            <div class="pf-toolbar" id="pfToolbar">
                                <button type="button" data-cmd="bold" title="Bold"><b>B</b></button>
                                <button type="button" data-cmd="italic" title="Italic"><i>I</i></button>
                                <button type="button" data-cmd="underline" title="Underline"><u>U</u></button>
                                <button type="button" data-cmd="strikeThrough" title="Strike"><s>S</s></button>
                                <button type="button" data-cmd="insertUnorderedList" title="Bullets">• List</button>
                                <button type="button" data-cmd="insertOrderedList" title="Numbers">1. List</button>
                                <button type="button" data-cmd="createLink" title="Link">Link</button>
                            </div>
                            <div class="pf-editor-body" id="pfEditor" contenteditable="true" data-placeholder="Enter your messages..."><?= $descHtml !== '' ? $descHtml : '' ?></div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Step 2 -->
            <section class="pf-step" data-step="2" hidden>
                <div class="pf-card">
                    <div class="pf-card-head">
                        <span class="pf-card-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg></span>
                        <h2>Product Gallery</h2>
                    </div>

                    <?php $hasThumb = !empty($v('thumbnail')); ?>
                    <div class="pg-upload<?= $hasThumb ? ' is-filled' : '' ?>" id="pfThumbBox">
                        <div class="pg-upload__head">
                            <span class="pg-upload__badge" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
                            </span>
                            <h3>Product Thumbnail</h3>
                        </div>
                        <div class="pg-upload__card">
                            <div class="pg-upload__topline">
                                <span class="pg-upload__label">Main product image</span>
                                <span class="pg-upload__tag">Recommended</span>
                            </div>
                            <div class="pg-upload__row">
                                <div class="pg-upload__preview" id="pfThumbPreview">
                                    <?php if ($hasThumb): ?>
                                        <img src="../<?= e((string)$v('thumbnail')) ?>" alt="Thumbnail" id="pfThumbImg">
                                    <?php else: ?>
                                        <span class="pg-upload__ph" id="pfThumbEmpty" aria-hidden="true">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="pg-upload__side">
                                    <label class="pg-upload__pick" for="pfThumb" id="pfThumbDrop">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M16 16l-4-4-4 4"/><path d="M12 12v9"/><path d="M20.39 18.39A5 5 0 0018 9h-1.26A8 8 0 103 16.3"/></svg>
                                        <span>Choose thumbnail</span>
                                    </label>
                                    <input type="file" name="thumbnail" id="pfThumb" accept="image/png,image/jpeg,image/webp,image/gif" style="position:absolute;width:1px;height:1px;opacity:0;overflow:hidden;clip:rect(0,0,0,0)">
                                    <p class="pg-upload__file" id="pfThumbName"><?= $hasThumb ? e(basename((string)$v('thumbnail'))) : 'No file selected' ?></p>
                                    <p class="pg-upload__hint">PNG, JPG or WebP · max 2 MB</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="pg-upload pg-upload--gallery" id="pfGalleryBox">
                        <div class="pg-upload__head">
                            <span class="pg-upload__badge" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M16 16l-4-4-4 4"/><path d="M12 12v9"/><path d="M20.39 18.39A5 5 0 0018 9h-1.26A8 8 0 103 16.3"/></svg>
                            </span>
                            <h3>Image Gallery</h3>
                        </div>
                        <div class="pg-upload__card">
                            <div class="pg-upload__topline">
                                <span class="pg-upload__label">Additional gallery images</span>
                                <span class="pg-upload__tag pg-upload__tag--muted">Multiple</span>
                            </div>

                            <label class="pg-dropzone" for="pfGallery" id="pfGalleryDrop">
                                <span class="pg-dropzone__ico">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M16 16l-4-4-4 4"/><path d="M12 12v9"/><path d="M20.39 18.39A5 5 0 0018 9h-1.26A8 8 0 103 16.3"/></svg>
                                </span>
                                <strong>Click to upload gallery images</strong>
                                <span>or drag &amp; drop here</span>
                                <em>PNG, JPG or WebP · max 2 MB each</em>
                            </label>
                            <input type="file" name="gallery[]" id="pfGallery" accept="image/png,image/jpeg,image/webp,image/gif" multiple style="position:absolute;width:1px;height:1px;opacity:0;overflow:hidden;clip:rect(0,0,0,0)">

                            <div class="pg-gallery-grid" id="pfGalleryPreview"></div>

                            <?php if ($gallery): ?>
                            <div class="pg-gallery-existing">
                                <div class="pg-gallery-existing__label">Saved images</div>
                                <div class="pg-gallery-grid">
                                    <?php foreach ($gallery as $img): ?>
                                    <label class="pg-gal-card">
                                        <img src="../<?= e($img['image_path']) ?>" alt="">
                                        <span class="pg-gal-card__remove">
                                            <input type="checkbox" name="remove_gallery[]" value="<?= (int)$img['id'] ?>"> Remove
                                        </span>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Step 3 -->
            <section class="pf-step" data-step="3" hidden>
                <div class="pf-card">
                    <div class="pf-card-head">
                        <span class="pf-card-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg></span>
                        <h2>Product Categories</h2>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Category</label>
                            <select name="category_id" id="category_id">
                                <option value="">— Select Category —</option>
                                <?php foreach ($categories as $c): ?>
                                <option value="<?= (int)$c['id'] ?>" <?= $selectedCategory === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Sub-Category</label>
                            <select name="subcategory_id" id="subcategory_id">
                                <option value="">— Select Sub-Category —</option>
                                <?php foreach ($subcategories as $s): ?>
                                <option value="<?= (int)$s['id'] ?>" data-category="<?= (int)$s['category_id'] ?>" <?= $selectedSub === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Size</label>
                            <select name="size_id">
                                <option value="">— None —</option>
                                <?php foreach ($sizes as $s): ?>
                                <option value="<?= (int)$s['id'] ?>" <?= ((int)$v('size_id', 0) === (int)$s['id']) ? 'selected' : '' ?>><?= e($s['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Color</label>
                            <select name="color_id">
                                <option value="">— None —</option>
                                <?php foreach ($colors as $c): ?>
                                <option value="<?= (int)$c['id'] ?>" <?= ((int)$v('color_id', 0) === (int)$c['id']) ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Listing Status</label>
                            <select name="status">
                                <option value="active" <?= $v('status', 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= $v('status', '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Step 4 -->
            <section class="pf-step" data-step="4" hidden>
                <div class="pf-card">
                    <div class="pf-card-head">
                        <span class="pf-card-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg></span>
                        <h2>Selling Prices</h2>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>MRP</label>
                            <input type="number" step="0.01" min="0" name="mrp" id="pfMrp" value="<?= e((string)$v('mrp', '0')) ?>">
                        </div>
                        <div class="form-group">
                            <label>Selling Price *</label>
                            <input type="number" step="0.01" min="0" name="price" id="pfPrice" value="<?= e((string)$v('price', '0')) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Discount % <span class="pf-auto-tag">Auto</span></label>
                            <input type="number" step="0.01" name="discount_percent" id="pfDiscount" value="<?= e((string)$v('discount_percent', '0')) ?>" readonly class="pf-readonly">
                            <p class="pf-help">MRP aur Selling Price se auto calculate hota hai.</p>
                        </div>
                    </div>
                </div>

                <div class="pf-card">
                    <div class="pf-card-head">
                        <span class="pf-card-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg></span>
                        <div>
                            <h2>Offer strip</h2>
                            <p class="pf-card-sub">Flash line, countdown timer display, aur bank copy — optional.</p>
                        </div>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Flash Offer Text</label>
                            <input type="text" name="offer_flash_text" value="<?= e((string)$v('offer_flash_text', '')) ?>" placeholder="e.g., Flash deal ends in">
                        </div>
                        <div class="form-group">
                            <label>Countdown (HH:MM:SS)</label>
                            <input type="text" name="offer_countdown" value="<?= e((string)$v('offer_countdown', '')) ?>" placeholder="48:00:00" pattern="^\d{1,3}:[0-5]\d:[0-5]\d$" title="Format: HH:MM:SS">
                        </div>
                        <div class="form-group" style="grid-column:1/-1">
                            <label>Card / Bank Offer Text</label>
                            <input type="text" name="offer_bank_text" value="<?= e((string)$v('offer_bank_text', '')) ?>" placeholder="Extra 10% off with HDFC card">
                        </div>
                    </div>
                </div>
            </section>

            <!-- Step 5 -->
            <section class="pf-step" data-step="5" hidden>
                <div class="pf-card">
                    <div class="pf-card-head">
                        <span class="pf-card-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14.7 6.3a1 1 0 000 1.4l1.6 1.6a1 1 0 001.4 0l3.77-3.77a6 6 0 01-7.94 7.94l-6.91 6.91a2.12 2.12 0 01-3-3l6.91-6.91a6 6 0 017.94-7.94l-3.76 3.76z"/></svg></span>
                        <h2>Advance</h2>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Stock Qty</label>
                            <input type="number" name="stock_qty" value="<?= (int)$v('stock_qty', 0) ?>">
                        </div>
                        <div class="form-group">
                            <label>Meta Title</label>
                            <input type="text" name="meta_title" value="<?= e((string)$v('meta_title', '')) ?>" placeholder="SEO title">
                        </div>
                        <div class="form-group" style="grid-column:1/-1">
                            <label>Meta Description</label>
                            <textarea name="meta_description" rows="3" placeholder="SEO description"><?= e((string)$v('meta_description', '')) ?></textarea>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Step 6 -->
            <section class="pf-step" data-step="6" hidden>
                <div class="pf-card">
                    <div class="pf-card-head">
                        <span class="pf-card-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg></span>
                        <h2>Shipping</h2>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Weight (kg)</label>
                            <input type="number" step="0.01" name="weight" value="<?= e((string)$v('weight', '0')) ?>">
                        </div>
                        <div class="form-group">
                            <label>Length (cm)</label>
                            <input type="number" step="0.01" name="length" value="<?= e((string)$v('length', '0')) ?>">
                        </div>
                        <div class="form-group">
                            <label>Width (cm)</label>
                            <input type="number" step="0.01" name="width" value="<?= e((string)$v('width', '0')) ?>">
                        </div>
                        <div class="form-group">
                            <label>Height (cm)</label>
                            <input type="number" step="0.01" name="height" value="<?= e((string)$v('height', '0')) ?>">
                        </div>
                    </div>
                </div>
            </section>

            <div class="pf-actions">
                <button type="button" class="btn btn-outline is-hidden" id="pfPrev" hidden>Previous</button>
                <div class="pf-actions-right">
                    <a href="product-details.php" class="btn btn-outline is-hidden" id="pfCancel" hidden>Cancel</a>
                    <button type="button" class="btn btn-primary" id="pfNext">Next Progression</button>
                    <button type="submit" class="btn btn-primary is-hidden" id="pfSubmit" hidden><?= !empty($edit['id']) ? 'Update Product' : 'Save Product' ?></button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
window.PF_SUBCATEGORIES = <?= json_encode(array_map(static function ($s) {
    return ['id' => (int)$s['id'], 'category_id' => (int)$s['category_id'], 'name' => $s['name']];
}, $subcategories), JSON_UNESCAPED_UNICODE) ?>;
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
