<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/functions.php';
require_installed();
start_session();

$criteria = db()->query('SELECT id, name FROM rating_criteria WHERE is_active = 1 ORDER BY sort_order, id')->fetchAll();
$errors = [];
$done = false;
$old = ['name' => '', 'comment' => '', 'scores' => []];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $old['name'] = mb_substr(post('name'), 0, 60);
    $old['comment'] = mb_substr(post('comment'), 0, 2000);
    $scoresIn = $_POST['score'] ?? [];
    if (!is_array($scoresIn)) {
        $scoresIn = [];
    }
    foreach ($criteria as $c) {
        $v = (int) ($scoresIn[$c['id']] ?? 0);
        if ($v >= 1 && $v <= 5) {
            $old['scores'][(int) $c['id']] = $v;
        }
    }

    if (post('website') !== '') {                        // honeypot
        $errors[] = t('Geçersiz gönderim.');
    }
    if (mb_strlen($old['comment']) < 5) {
        $errors[] = t('Yorumunuz en az 5 karakter olmalı.');
    }
    if ($criteria && count($old['scores']) < count($criteria)) {
        $errors[] = t('Lütfen tüm kriterleri puanlayın.');
    }
    if (!$errors) {
        $pdo = db();
        try {
            // BEGIN IMMEDIATE: yazma kilidi alinir, ayni anda gelen istekler siraya girer (rate limit yarisi onlenir)
            $pdo->exec('BEGIN IMMEDIATE');
            $limitError = submission_limit_error($pdo, 'reviews', 'reviewed');
            if ($limitError !== null) {
                $pdo->exec('ROLLBACK');
                $errors[] = t($limitError);
            }
        } catch (Throwable $t) {
            if ($pdo->inTransaction()) {
                $pdo->exec('ROLLBACK');
            }
            error_log('Yorum limit kontrolu hatasi: ' . $t->getMessage());
            $errors[] = t('Bir hata oluştu, lütfen tekrar deneyin.');
        }
    }
    if (!$errors) {
        try {
            $pdo->prepare('INSERT INTO reviews (product_id, name, comment, ip, created_at) VALUES (NULL, ?, ?, ?, ?)')
                ->execute([$old['name'], $old['comment'], ip_bucket(), date('Y-m-d H:i:s')]);   // uygulama saat dilimiyle
            $rid = (int) $pdo->lastInsertId();
            $ins = $pdo->prepare('INSERT INTO review_scores (review_id, criteria_id, score) VALUES (?, ?, ?)');
            foreach ($old['scores'] as $cid => $score) {
                $ins->execute([$rid, $cid, $score]);
            }
            $pdo->exec('COMMIT');
            mark_submission('reviewed');
            $done = true;
        } catch (Throwable $t) {
            if ($pdo->inTransaction()) {
                $pdo->exec('ROLLBACK');
            }
            error_log('Yorum kaydi hatasi: ' . $t->getMessage());
            $errors[] = t('Bir hata oluştu, lütfen tekrar deneyin.');
        }
    }
}

$pageTitle = t('Değerlendirme');
$backUrl = url('');
$activeNav = 'review';
require __DIR__ . '/inc/layout_top.php';
?>
<?php if ($done): ?>
    <div class="card thanks">
        <div class="big">🙏</div>
        <h1><?= e(t('Teşekkür ederiz!')) ?></h1>
        <p><?= e(t('Görüşleriniz bizim için çok değerli. Yorumunuz işletmemize iletildi.')) ?></p>
        <a class="btn btn-primary" href="<?= e(url('')) ?>"><?= e(t('Menüye dön')) ?></a>
    </div>
<?php else: ?>
<div class="card" style="max-width:640px;margin:0 auto">
    <h1><?= e(t('Bizi değerlendirin')) ?></h1>
    <p class="lead"><?= e(t('Restoranımızı puanlayın ve düşüncelerinizi paylaşın. Yorumlar yalnızca işletme tarafından görülür. Tek bir ürünü puanlamak için o ürünün sayfasını kullanabilirsiniz.')) ?></p>
    <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
    <form method="post" novalidate>
        <?= csrf_field() ?>
        <input class="honey" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">

        <?php if ($criteria): ?>
        <div class="criteria-list">
            <?php foreach ($criteria as $c): $cid = (int) $c['id']; $cur = $old['scores'][$cid] ?? 0; ?>
                <div class="criterion">
                    <span class="cname"><?= e(t($c['name'])) ?></span>
                    <span class="star-input" role="radiogroup" aria-label="<?= e(t($c['name'])) ?>">
                        <?php for ($s = 5; $s >= 1; $s--): $iid = 'c' . $cid . 's' . $s; ?>
                            <input type="radio" id="<?= $iid ?>" name="score[<?= $cid ?>]" value="<?= $s ?>"<?= $cur === $s ? ' checked' : '' ?>>
                            <label for="<?= $iid ?>" title="<?= e(t('%d yıldız', $s)) ?>"></label>
                        <?php endfor; ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="form-group">
            <label for="name"><?= e(t('Adınız')) ?> <span style="font-weight:400;color:var(--muted)"><?= e(t('(isteğe bağlı)')) ?></span></label>
            <input type="text" id="name" name="name" maxlength="60" value="<?= e($old['name']) ?>" placeholder="<?= e(t('Anonim')) ?>">
        </div>
        <div class="form-group">
            <label for="comment"><?= e(t('Yorumunuz')) ?></label>
            <textarea id="comment" name="comment" maxlength="2000" required placeholder="<?= e(t('Neyi beğendiniz, neyi geliştirebiliriz?')) ?>"><?= e($old['comment']) ?></textarea>
        </div>
        <button type="submit" class="btn btn-primary btn-block"><?= e(t('Gönder')) ?></button>
        <?php if (kvkk_text() !== ''): ?>
            <p class="form-note"><?= e(t('Gönderdiğiniz bilgiler kişisel verilerin korunması kapsamında işlenir.')) ?> <a href="<?= e(url('yasal.php#kvkk')) ?>"><?= e(t('Aydınlatma metni')) ?></a></p>
        <?php endif; ?>
    </form>
</div>
<?php endif; ?>
<?php require __DIR__ . '/inc/layout_bottom.php'; ?>
