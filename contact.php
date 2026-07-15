<?php
require_once __DIR__ . '/config/database.php';

$company = setting('company_name', 'Binary MLM');
$formEnabled = setting('contact_form_enabled', '1') === '1';

$contact = [
    'person' => setting('contact_person', 'Support Team'),
    'phone' => setting('contact_phone'),
    'whatsapp' => preg_replace('/\D+/', '', setting('contact_whatsapp')),
    'email' => setting('contact_email', setting('support_email', 'support@binarymlm.com')),
    'alt_phone' => setting('contact_alt_phone'),
    'address' => setting('contact_address'),
    'city' => setting('contact_city'),
    'state' => setting('contact_state'),
    'country' => setting('contact_country', 'India'),
    'pincode' => setting('contact_pincode'),
    'hours' => setting('contact_hours'),
    'map_url' => setting('contact_map_url'),
    'facebook' => setting('contact_facebook'),
    'instagram' => setting('contact_instagram'),
    'twitter' => setting('contact_twitter'),
    'youtube' => setting('contact_youtube'),
    'telegram' => setting('contact_telegram'),
];

$fullAddress = trim(implode(', ', array_filter([
    $contact['address'],
    $contact['city'],
    $contact['state'],
    $contact['pincode'],
    $contact['country'],
])));

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $formEnabled) {
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $subject = trim((string) ($_POST['subject'] ?? ''));
    $message = trim((string) ($_POST['message'] ?? ''));

    if ($name === '') {
        $errors[] = 'Name is required.';
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email is required.';
    }
    if ($message === '' || strlen($message) < 10) {
        $errors[] = 'Message must be at least 10 characters.';
    }

    if (!$errors) {
        try {
            $pdo->prepare('INSERT INTO contact_inquiries (name, email, phone, subject, message, status, ip_address) VALUES (?,?,?,?,?,?,?)')
                ->execute([
                    $name,
                    $email,
                    $phone !== '' ? $phone : null,
                    $subject !== '' ? $subject : null,
                    $message,
                    'new',
                    $_SERVER['REMOTE_ADDR'] ?? null,
                ]);
            $success = true;
        } catch (Throwable $e) {
            $errors[] = 'Could not send your message. Please try again later.';
        }
    }
}

$social = array_filter([
    'Facebook' => $contact['facebook'],
    'Instagram' => $contact['instagram'],
    'Twitter' => $contact['twitter'],
    'YouTube' => $contact['youtube'],
    'Telegram' => $contact['telegram'],
]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us | <?= e($company) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/admin.css">
    <link rel="stylesheet" href="assets/css/contact.css">
</head>
<body class="contact-page">
    <header class="contact-top">
        <div class="contact-top-inner">
            <a href="admin/login.php" class="contact-brand"><?= e($company) ?></a>
            <nav>
                <a href="admin/login.php">Admin Login</a>
                <a href="member/index.php">Member</a>
            </nav>
        </div>
    </header>

    <main class="contact-main">
        <div class="contact-hero">
            <h1>Contact Us</h1>
            <p>Reach <?= e($contact['person'] ?: $company) ?> — we are happy to help.</p>
        </div>

        <div class="contact-layout">
            <aside class="contact-info-card">
                <h2>Get in touch</h2>
                <?php if ($contact['person']): ?>
                <div class="ci-row">
                    <span class="ci-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
                    <div>
                        <strong>Contact Person</strong>
                        <span><?= e($contact['person']) ?></span>
                    </div>
                </div>
                <?php endif; ?>
                <?php if ($contact['phone']): ?>
                <div class="ci-row">
                    <span class="ci-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/></svg></span>
                    <div>
                        <strong>Phone</strong>
                        <span><a href="tel:<?= e(preg_replace('/\s+/', '', $contact['phone'])) ?>"><?= e($contact['phone']) ?></a></span>
                    </div>
                </div>
                <?php endif; ?>
                <?php if ($contact['whatsapp']): ?>
                <div class="ci-row">
                    <span class="ci-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z"/></svg></span>
                    <div>
                        <strong>WhatsApp</strong>
                        <span><a href="https://wa.me/<?= e($contact['whatsapp']) ?>" target="_blank" rel="noopener">Chat on WhatsApp</a></span>
                    </div>
                </div>
                <?php endif; ?>
                <?php if ($contact['email']): ?>
                <div class="ci-row">
                    <span class="ci-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg></span>
                    <div>
                        <strong>Email</strong>
                        <span><a href="mailto:<?= e($contact['email']) ?>"><?= e($contact['email']) ?></a></span>
                    </div>
                </div>
                <?php endif; ?>
                <?php if ($contact['alt_phone']): ?>
                <div class="ci-row">
                    <span class="ci-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg></span>
                    <div>
                        <strong>Alternate</strong>
                        <span><?= e($contact['alt_phone']) ?></span>
                    </div>
                </div>
                <?php endif; ?>
                <?php if ($fullAddress): ?>
                <div class="ci-row">
                    <span class="ci-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg></span>
                    <div>
                        <strong>Address</strong>
                        <span><?= e($fullAddress) ?></span>
                        <?php if ($contact['map_url']): ?>
                        <a class="map-link" href="<?= e($contact['map_url']) ?>" target="_blank" rel="noopener">Open in Maps</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                <?php if ($contact['hours']): ?>
                <div class="ci-row">
                    <span class="ci-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></span>
                    <div>
                        <strong>Hours</strong>
                        <span><?= e($contact['hours']) ?></span>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($social): ?>
                <div class="contact-social">
                    <?php foreach ($social as $label => $url): ?>
                    <a href="<?= e($url) ?>" target="_blank" rel="noopener"><?= e($label) ?></a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </aside>

            <section class="contact-form-card">
                <h2>Send a message</h2>
                <?php if (!$formEnabled): ?>
                    <div class="alert alert-info">Contact form is currently disabled. Please use phone or email.</div>
                <?php elseif ($success): ?>
                    <div class="alert alert-success">Thank you! Your message has been sent. Our team will contact you soon.</div>
                    <a href="contact.php" class="btn btn-outline">Send another message</a>
                <?php else: ?>
                    <?php if ($errors): ?>
                    <div class="alert alert-error"><?= e(implode(' ', $errors)) ?></div>
                    <?php endif; ?>
                    <form method="post" class="contact-form">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Your Name *</label>
                                <input type="text" name="name" value="<?= e($_POST['name'] ?? '') ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Email *</label>
                                <input type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Phone</label>
                                <input type="text" name="phone" value="<?= e($_POST['phone'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label>Subject</label>
                                <input type="text" name="subject" value="<?= e($_POST['subject'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Message *</label>
                            <textarea name="message" rows="5" required><?= e($_POST['message'] ?? '') ?></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Send Message</button>
                    </form>
                <?php endif; ?>
            </section>
        </div>
    </main>

    <footer class="contact-foot">
        &copy; <?= date('Y') ?> <?= e($company) ?>. All rights reserved.
    </footer>
</body>
</html>
