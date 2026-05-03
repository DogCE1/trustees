<?php
include "Includes/db.php";

if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        header("Location: Admin/dashboard.php");
    } else {
        header("Location: Listings/browse.php");
    }
    exit();
}

$sql = "SELECT * FROM listings
        WHERE status = 'verified' AND user_id IS NOT NULL
        ORDER BY created_at DESC
        LIMIT 8";

$result = $conn->query($sql);
$listings = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $listings[] = $row;
    }
}

include "Includes/header.php";

function listing_glyph($title) {
    $clean = preg_replace('/[^A-Za-z0-9 ]/', '', (string)$title);
    $words = preg_split('/\s+/', trim($clean));
    if (!empty($words[0])) {
        $first = strtoupper(substr($words[0], 0, 3));
        return $first ?: '·';
    }
    return '·';
}
function format_rand($n) {
    return number_format((float)$n, 0, ',', "\u{2009}");
}
?>

<div class="page">
    <section class="tr-hero">
        <div>
            <span class="pill pill-verified">✓ Verified marketplace</span>
            <h1>The middle man<br>you can trust.</h1>
            <p>Buy and sell safely. Every item is dropped off, inspected and verified at a Trustees location before money changes hands.</p>
            <div class="tr-hero-actions">
                <a href="Listings/create.php" class="btn btn-accent btn-lg"><i class="fas fa-circle-plus"></i> List an item</a>
                <a href="Listings/browse.php" class="btn btn-ghost-light btn-lg">Browse listings →</a>
            </div>
        </div>
        <aside class="tr-hero-howitworks">
            <div class="eyebrow">How it works</div>
            <ol>
                <li>
                    <span class="step">1</span>
                    <div>
                        <div class="step-title">Seller lists &amp; drops off</div>
                        <div class="step-desc">List online, drop at nearest Trustees.</div>
                    </div>
                </li>
                <li>
                    <span class="step">2</span>
                    <div>
                        <div class="step-title">We verify the item</div>
                        <div class="step-desc">Staff inspect it matches the listing.</div>
                    </div>
                </li>
                <li>
                    <span class="step">3</span>
                    <div>
                        <div class="step-title">Buyer collects safely</div>
                        <div class="step-desc">Collect in-store or request delivery.</div>
                    </div>
                </li>
            </ol>
        </aside>
    </section>

    <div class="section-head">
        <h2>Latest verified listings</h2>
        <a href="Listings/browse.php" class="link-arrow">View all →</a>
    </div>

    <?php if (empty($listings)): ?>
        <p class="tr-muted">No listings yet. Be the first to sell.</p>
    <?php else: ?>
        <div class="listings">
            <?php foreach ($listings as $listing): ?>
                <a class="listing-card" href="Listings/view.php?id=<?= (int)$listing['id'] ?>" data-category="<?= htmlspecialchars($listing['category']) ?>">
                    <div class="listing-card-media">
                        <?php if (!empty($listing['image'])): ?>
                            <img src="<?= htmlspecialchars($listing['image']) ?>" alt="">
                        <?php else: ?>
                            <span class="listing-card-glyph"><?= htmlspecialchars(listing_glyph($listing['title'])) ?></span>
                        <?php endif; ?>
                        <span class="pill pill-verified listing-card-pill">✓ Verified</span>
                    </div>
                    <div class="listing-card-body">
                        <h3 class="listing-card-title"><?= htmlspecialchars($listing['title']) ?></h3>
                        <div class="listing-card-row">
                            <span class="listing-card-meta"><?= htmlspecialchars($listing['category']) ?></span>
                            <span class="price">R<?= format_rand($listing['price']) ?></span>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php
include "Includes/footer.php";
?>