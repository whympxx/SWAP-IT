<?php
require_once 'config/database.php';
require_once 'config/functions.php';

$conn = getDBConnection();
$current_user = getCurrentUser($conn);

// Parameter pencarian & filter
$search = cleanInput($_GET['search'] ?? '');
$category = cleanInput($_GET['category'] ?? '');
$condition = cleanInput($_GET['condition'] ?? '');
$sort = $_GET['sort'] ?? 'newest';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 12;

$categories = ITEM_CATEGORIES;
$conditions = ITEM_CONDITIONS;
$sort_options = [
    'newest' => ['label' => 'Terbaru', 'sql' => 'i.created_at DESC'],
    'oldest' => ['label' => 'Terlama', 'sql' => 'i.created_at ASC'],
    'title'  => ['label' => 'Nama A-Z', 'sql' => 'i.title ASC'],
];
if (!is_string($sort) || !isset($sort_options[$sort])) {
    $sort = 'newest';
}
if (!in_array($category, $categories, true)) {
    $category = '';
}
if (!in_array($condition, $conditions, true)) {
    $condition = '';
}

$where = " WHERE i.status = 'available'";
$params = [];
$types = '';

if ($search !== '') {
    $where .= " AND (i.title LIKE ? OR i.description LIKE ?)";
    $searchParam = '%' . addcslashes($search, '%_\\') . '%';
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= 'ss';
}
if ($category !== '') {
    $where .= " AND i.category = ?";
    $params[] = $category;
    $types .= 's';
}
if ($condition !== '') {
    $where .= " AND i.condition_item = ?";
    $params[] = $condition;
    $types .= 's';
}

// Total untuk pagination
$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM items i" . $where);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total_items = (int)$stmt->get_result()->fetch_assoc()['total'];
$total_pages = max(1, (int)ceil($total_items / $per_page));
$page = min($page, $total_pages);
$offset = ($page - 1) * $per_page;

$query = "SELECT i.*, u.username, u.full_name, u.university
          FROM items i
          JOIN users u ON i.user_id = u.id" . $where .
         " ORDER BY " . $sort_options[$sort]['sql'] . " LIMIT ? OFFSET ?";
$stmt = $conn->prepare($query);
$list_params = array_merge($params, [$per_page, $offset]);
$stmt->bind_param($types . 'ii', ...$list_params);
$stmt->execute();
$items = $stmt->get_result();

// Statistik nyata (sebelumnya angka "500+", "100+" ditulis manual)
$stats = $conn->query("SELECT
    (SELECT COUNT(*) FROM items WHERE status = 'available') AS items_count,
    (SELECT COUNT(*) FROM users) AS users_count,
    (SELECT COUNT(*) FROM offers WHERE status = 'completed') AS trades_count")->fetch_assoc();

// Barang yang sudah ditawar user (penawaran masih menunggu)
$my_offered_item_ids = [];
if (isLoggedIn()) {
    $stmt = $conn->prepare("SELECT item_wanted_id FROM offers WHERE sender_id = ? AND status = 'pending'");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $my_offered_item_ids[(int)$row['item_wanted_id']] = true;
    }
}

function pageUrl($page) {
    $query = $_GET;
    $query['page'] = $page;
    return '?' . http_build_query($query) . '#items';
}

$is_filtered = $search !== '' || $category !== '' || $condition !== '' || $sort !== 'newest';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SWAP-IT - Platform Barter Barang Bekas</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="hero-section">
        <div class="hero-background-shapes">
            <div class="shape shape-1"></div>
            <div class="shape shape-2"></div>
            <div class="shape shape-3"></div>
            <div class="shape shape-4"></div>
        </div>

        <div class="container">
            <div class="hero-grid">
                <div class="hero-content">
                    <div class="hero-badge">
                        <i class="fas fa-leaf"></i> Ramah Lingkungan
                    </div>

                    <h1 class="hero-title">
                        Tukar Barang Bekas,<br>
                        <span class="text-gradient">Hemat & Ramah Lingkungan</span>
                    </h1>

                    <p class="hero-subtitle">
                        Platform barter khusus mahasiswa untuk menukar barang yang masih layak pakai.
                        Hemat uang, kurangi sampah, dapatkan barang baru!
                    </p>

                    <div class="hero-features">
                        <div class="hero-feature-item">
                            <i class="fas fa-check-circle"></i>
                            <span>100% Gratis</span>
                        </div>
                        <div class="hero-feature-item">
                            <i class="fas fa-check-circle"></i>
                            <span>Aman & Terpercaya</span>
                        </div>
                        <div class="hero-feature-item">
                            <i class="fas fa-check-circle"></i>
                            <span>Komunitas Mahasiswa</span>
                        </div>
                    </div>

                    <?php if (!isLoggedIn()): ?>
                        <div class="hero-cta">
                            <a href="auth/register.php" class="btn btn-primary btn-lg">
                                <i class="fas fa-user-plus"></i> Mulai Sekarang
                            </a>
                            <a href="auth/login.php" class="btn btn-outline btn-lg">
                                <i class="fas fa-sign-in-alt"></i> Login
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="hero-cta">
                            <a href="pages/post-item.php" class="btn btn-primary btn-lg">
                                <i class="fas fa-plus"></i> Posting Barang
                            </a>
                            <a href="#items" class="btn btn-outline btn-lg">
                                <i class="fas fa-search"></i> Cari Barang
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="hero-illustration">
                    <div class="hero-swap-animation">
                        <div class="swap-item swap-item-left">
                            <div class="item-box">
                                <i class="fas fa-book"></i>
                            </div>
                            <div class="item-label">Buku</div>
                        </div>

                        <div class="swap-arrows">
                            <i class="fas fa-exchange-alt"></i>
                        </div>

                        <div class="swap-item swap-item-right">
                            <div class="item-box">
                                <i class="fas fa-laptop"></i>
                            </div>
                            <div class="item-label">Laptop</div>
                        </div>
                    </div>

                    <div class="floating-items">
                        <div class="floating-item" style="--delay: 0s; --x: -20px; --y: -30px;">
                            <i class="fas fa-tshirt"></i>
                        </div>
                        <div class="floating-item" style="--delay: 0.5s; --x: 30px; --y: -20px;">
                            <i class="fas fa-gamepad"></i>
                        </div>
                        <div class="floating-item" style="--delay: 1s; --x: -30px; --y: 20px;">
                            <i class="fas fa-camera"></i>
                        </div>
                        <div class="floating-item" style="--delay: 1.5s; --x: 25px; --y: 30px;">
                            <i class="fas fa-headphones"></i>
                        </div>
                        <div class="floating-item" style="--delay: 2s; --x: 0px; --y: -40px;">
                            <i class="fas fa-bicycle"></i>
                        </div>
                    </div>

                    <div class="hero-stats">
                        <div class="stat-bubble stat-1">
                            <div class="stat-number"><?= (int)$stats['items_count'] ?></div>
                            <div class="stat-text">Barang</div>
                        </div>
                        <div class="stat-bubble stat-2">
                            <div class="stat-number"><?= (int)$stats['users_count'] ?></div>
                            <div class="stat-text">Mahasiswa</div>
                        </div>
                        <div class="stat-bubble stat-3">
                            <div class="stat-number"><?= (int)$stats['trades_count'] ?></div>
                            <div class="stat-text">Barter Selesai</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container main-content">
        <!-- Search and Filter -->
        <div class="search-filter-section" id="items">
            <form method="GET" action="" class="search-form">
                <div class="search-input-group">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" placeholder="Cari barang..."
                           value="<?= e($search) ?>">
                </div>

                <select name="category" class="category-select" aria-label="Kategori">
                    <option value="">Semua Kategori</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= e($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
                    <?php endforeach; ?>
                </select>

                <select name="condition" class="category-select" aria-label="Kondisi">
                    <option value="">Semua Kondisi</option>
                    <?php foreach ($conditions as $cond): ?>
                        <option value="<?= e($cond) ?>" <?= $condition === $cond ? 'selected' : '' ?>><?= e($cond) ?></option>
                    <?php endforeach; ?>
                </select>

                <select name="sort" class="category-select" aria-label="Urutkan">
                    <?php foreach ($sort_options as $key => $opt): ?>
                        <option value="<?= $key ?>" <?= $sort === $key ? 'selected' : '' ?>><?= e($opt['label']) ?></option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter"></i> Filter
                </button>

                <?php if ($is_filtered): ?>
                    <a href="index.php#items" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Reset
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Items Grid -->
        <div class="items-section">
            <div class="section-header">
                <h2>Barang Tersedia <span class="count-pill"><?= $total_items ?></span></h2>
                <?php if (isLoggedIn()): ?>
                    <a href="pages/post-item.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Posting Barang
                    </a>
                <?php endif; ?>
            </div>

            <div class="items-grid">
                <?php if ($items && $items->num_rows > 0): ?>
                    <?php while ($item = $items->fetch_assoc()): ?>
                        <?php
                            $is_mine = isLoggedIn() && $item['user_id'] == $_SESSION['user_id'];
                            $already_offered = isset($my_offered_item_ids[(int)$item['id']]);
                        ?>
                        <div class="item-card">
                            <a class="item-image" href="pages/item-detail.php?id=<?= (int)$item['id'] ?>">
                                <img src="<?= UPLOAD_URL ?>items/<?= e($item['photo']) ?>"
                                     alt="<?= e($item['title']) ?>" loading="lazy">
                                <span class="item-category"><?= e($item['category']) ?></span>
                                <?php if ($is_mine): ?>
                                    <span class="item-status status-available">Barang Anda</span>
                                <?php elseif ($already_offered): ?>
                                    <span class="item-status status-pending">Sudah Ditawar</span>
                                <?php endif; ?>
                            </a>
                            <div class="item-content">
                                <h3 class="item-title"><?= e($item['title']) ?></h3>
                                <p class="item-condition">
                                    <i class="fas fa-star"></i> <?= e($item['condition_item']) ?>
                                </p>
                                <p class="item-description"><?= e(excerpt($item['description'], 100)) ?></p>
                                <div class="item-meta">
                                    <a class="item-owner" href="pages/profile.php?id=<?= (int)$item['user_id'] ?>">
                                        <i class="fas fa-user"></i>
                                        <?= e($item['username']) ?>
                                    </a>
                                    <div class="item-date">
                                        <i class="fas fa-clock"></i>
                                        <?= timeAgo($item['created_at']) ?>
                                    </div>
                                </div>
                                <div class="item-actions">
                                    <a href="pages/item-detail.php?id=<?= (int)$item['id'] ?>" class="btn btn-primary btn-sm">
                                        <i class="fas fa-eye"></i> Lihat Detail
                                    </a>
                                    <?php if (isLoggedIn() && !$is_mine && !$already_offered): ?>
                                        <a href="pages/make-offer.php?item_id=<?= (int)$item['id'] ?>" class="btn btn-success btn-sm">
                                            <i class="fas fa-exchange-alt"></i> Tawarkan
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <h3>Tidak ada barang ditemukan</h3>
                        <p><?= $is_filtered ? 'Coba ubah filter pencarian Anda' : 'Jadilah yang pertama memposting barang!' ?></p>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($total_pages > 1): ?>
                <nav class="pagination" aria-label="Halaman">
                    <?php if ($page > 1): ?>
                        <a href="<?= e(pageUrl($page - 1)) ?>" class="page-link" aria-label="Sebelumnya"><i class="fas fa-chevron-left"></i></a>
                    <?php endif; ?>
                    <?php for ($p = max(1, $page - 2); $p <= min($total_pages, $page + 2); $p++): ?>
                        <a href="<?= e(pageUrl($p)) ?>" class="page-link <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
                    <?php endfor; ?>
                    <?php if ($page < $total_pages): ?>
                        <a href="<?= e(pageUrl($page + 1)) ?>" class="page-link" aria-label="Berikutnya"><i class="fas fa-chevron-right"></i></a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        </div>

        <!-- Cara Kerja -->
        <section class="how-it-works" id="cara-kerja">
            <h2>Cara Kerja SWAP-IT</h2>
            <div class="steps-grid">
                <div class="step-card">
                    <div class="step-icon"><i class="fas fa-camera"></i></div>
                    <h4>1. Posting Barang</h4>
                    <p>Foto dan deskripsikan barang bekas yang masih layak pakai.</p>
                </div>
                <div class="step-card">
                    <div class="step-icon"><i class="fas fa-exchange-alt"></i></div>
                    <h4>2. Ajukan Penawaran</h4>
                    <p>Pilih barang incaranmu dan tawarkan barangmu sebagai gantinya.</p>
                </div>
                <div class="step-card">
                    <div class="step-icon"><i class="fas fa-comments"></i></div>
                    <h4>3. Chat &amp; Bertemu</h4>
                    <p>Setelah diterima, atur tempat dan waktu ketemuan lewat chat.</p>
                </div>
                <div class="step-card">
                    <div class="step-icon"><i class="fas fa-check-double"></i></div>
                    <h4>4. Konfirmasi Selesai</h4>
                    <p>Tandai barter selesai agar riwayat dan statistikmu tercatat.</p>
                </div>
            </div>
        </section>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script src="<?= BASE_URL ?>assets/js/main.js"></script>
</body>
</html>
<?php $conn->close(); ?>
