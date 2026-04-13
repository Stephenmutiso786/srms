<?php
require_once('db/config.php');
require_once('const/school.php');
require_once('const/public_media.php');

$schoolName = (defined('WBName') && trim((string)WBName) !== '') ? (string)WBName : 'Kyandulu Primary School';
$schoolLogo = (defined('WBLogo') && trim((string)WBLogo) !== '') ? 'images/logo/' . trim((string)WBLogo) : 'images/logo/school_logo1711003619.png';
$schoolMotto = 'Nurturing Excellence Through CBC Education';
$schoolTagline = 'A trusted learning community shaping future-ready leaders.';
$schoolLocation = 'Kiunduani, Kibwezi West';
$schoolMapUrl = 'https://maps.app.goo.gl/fqhaetnW4G6hBmHs7';
$schoolPhone = '+25417876564';
$schoolEmail = (defined('WBEmail') && trim((string)WBEmail) !== '') ? (string)WBEmail : 'info@kyandulu.school';

$aboutText = $schoolName . ' is a learning institution in ' . $schoolLocation . ' committed to quality CBC education. We nurture every learner through academic excellence, character development, creativity, and practical life skills.';
$visionText = 'To develop responsible, skilled, and confident learners for tomorrow.';
$missionText = 'To deliver inclusive, learner-centered education through strong teaching, mentorship, and community partnership.';
$coreValues = 'Integrity, Discipline, Respect, Teamwork, and Excellence.';

$offers = [
	['title' => 'Academics', 'description' => 'Competency-Based Curriculum from PP1 to Grade 9.'],
	['title' => 'ICT Studies', 'description' => 'Foundational digital skills and guided computer learning.'],
	['title' => 'Sports & Clubs', 'description' => 'Co-curricular activities for fitness, teamwork, and talent growth.'],
	['title' => 'Day School', 'description' => 'Structured day-learning program with strong parent partnership.'],
	['title' => 'Transport & Meals', 'description' => 'Safe school transport and balanced meals for learners.'],
	['title' => 'Qualified Staff', 'description' => 'Dedicated teachers and mentorship-focused support team.'],
];

$facilities = [
	['title' => 'Science Labs', 'description' => 'Practical science exposure in structured learning spaces.'],
	['title' => 'Library', 'description' => 'Reading resources that support independent study habits.'],
	['title' => 'Computer Lab', 'description' => 'Guided access to computers and interactive learning tools.'],
	['title' => 'Playground', 'description' => 'Outdoor spaces for games, sports, and physical development.'],
	['title' => 'Transport System', 'description' => 'Reliable school transport for day learners.'],
	['title' => 'Safe Environment', 'description' => 'Secure and supervised campus for all learners.'],
];

$newsItems = [
	['title' => 'Upcoming Parents Meeting', 'description' => 'Term stakeholder engagement and learner progress briefing.'],
	['title' => 'Sports Day Preparations', 'description' => 'Inter-class games and athletics training currently underway.'],
	['title' => 'Academic Calendar Highlights', 'description' => 'Continuous assessment weeks and exam schedules published.'],
];

$conn = null;
try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Throwable $e) {
	$conn = null;
}

$offers = array_values(array_filter($offers, function ($item) {
	$title = strtolower(trim((string)($item['title'] ?? '')));
	return $title === '' || strpos($title, 'boarding') === false;
}));

if (isset($conn) && $conn instanceof PDO) {
	$schoolMotto = app_setting_get($conn, 'public_school_motto', $schoolMotto);
	$schoolTagline = app_setting_get($conn, 'public_school_tagline', $schoolTagline);
	$schoolLocation = app_setting_get($conn, 'public_school_location', $schoolLocation);
	$schoolMapUrl = app_setting_get($conn, 'public_school_location_map_url', $schoolMapUrl);
	$schoolPhone = app_setting_get($conn, 'public_school_phone', $schoolPhone);
	$schoolEmail = app_setting_get($conn, 'public_school_email', $schoolEmail);
	$aboutText = app_setting_get($conn, 'public_about_text', $aboutText);
	$visionText = app_setting_get($conn, 'public_vision_text', $visionText);
	$missionText = app_setting_get($conn, 'public_mission_text', $missionText);
	$coreValues = app_setting_get($conn, 'public_core_values', $coreValues);

	$rawOffers = app_setting_get($conn, 'public_offers_items', '');
	if (trim($rawOffers) !== '') {
		$offers = [];
		foreach (preg_split('/\r\n|\r|\n/', $rawOffers) as $line) {
			$parts = array_map('trim', explode('|', (string)$line, 2));
			if (empty($parts[0])) { continue; }
			$offers[] = ['title' => $parts[0], 'description' => $parts[1] ?? ''];
		}
	}

	$rawFacilities = app_setting_get($conn, 'public_facilities_items', '');
	if (trim($rawFacilities) !== '') {
		$facilities = [];
		foreach (preg_split('/\r\n|\r|\n/', $rawFacilities) as $line) {
			$parts = array_map('trim', explode('|', (string)$line, 2));
			if (empty($parts[0])) { continue; }
			$facilities[] = ['title' => $parts[0], 'description' => $parts[1] ?? ''];
		}
	}

	$rawNews = app_setting_get($conn, 'public_news_items', '');
	if (trim($rawNews) !== '') {
		$newsItems = [];
		foreach (preg_split('/\r\n|\r|\n/', $rawNews) as $line) {
			$parts = array_map('trim', explode('|', (string)$line, 2));
			if (empty($parts[0])) { continue; }
			$newsItems[] = ['title' => $parts[0], 'description' => $parts[1] ?? ''];
		}
	}
}

$captions = array(
	'A Conducive Learning Environment',
	'CBC Learning in Action',
	'Developing Future Leaders',
	'Modern Classrooms',
	'Sports and Co-curricular Activities',
	'Safe and Supportive School Community',
	'Growing Together Through Excellence',
	'Our Campus at a Glance',
	'Education Partnerships That Matter',
	'Community Service and Discipline'
);

$slides = array();
$galleryFiles = array();
$usesDbShowcase = false;

if ($conn instanceof PDO) {
	$dbSlides = app_public_showcase_images($conn);
	if (!empty($dbSlides)) {
		$usesDbShowcase = true;
		foreach ($dbSlides as $i => $row) {
			$slides[] = array(
				'src' => (string)$row['src'],
				'caption' => trim((string)($row['caption'] ?? ''))
			);
		}
	}
}

if (count($slides) === 0) {
	$galleryFiles = glob(__DIR__ . '/images/showcase/*.{jpg,jpeg,png,webp,JPG,JPEG,PNG,WEBP}', GLOB_BRACE);
	if (is_array($galleryFiles)) {
		foreach ($galleryFiles as $i => $file) {
			$slides[] = array(
				'src' => 'images/showcase/' . basename($file),
				'caption' => $captions[$i % count($captions)]
			);
		}
	}
}

if (count($slides) === 0) {
	for ($i = 0; $i < 4; $i++) {
		$slides[] = array(
			'src' => $schoolLogo,
			'caption' => $captions[$i % count($captions)]
		);
	}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo htmlspecialchars($schoolName); ?> | Main Website</title>
	<link rel="manifest" href="manifest.webmanifest">
	<meta name="theme-color" content="#006400">
	<link rel="apple-touch-icon" href="images/pwa/icon-192.png">
	<link rel="stylesheet" href="cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
	<style>
		:root {
			--brand-forest: #1f5f3f;
			--brand-mint: #e5f6ec;
			--brand-gold: #f2b544;
			--brand-charcoal: #1f2933;
			--brand-cream: #fffdf8;
			--card-shadow: 0 14px 30px rgba(14, 37, 27, 0.16);
		}

		* {
			box-sizing: border-box;
		}

		body {
			margin: 0;
			font-family: "Nunito", "Segoe UI", "Trebuchet MS", sans-serif;
			color: var(--brand-charcoal);
			background:
				radial-gradient(circle at 15% 8%, rgba(242, 181, 68, 0.28), transparent 30%),
				radial-gradient(circle at 90% 16%, rgba(31, 95, 63, 0.16), transparent 32%),
				linear-gradient(180deg, #f6fbf8, #ffffff 28%);
		}

		a {
			color: inherit;
			text-decoration: none;
		}

		.top-nav {
			position: sticky;
			top: 0;
			z-index: 40;
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: 1rem;
			padding: 0.85rem 1.2rem;
			background: rgba(255, 255, 255, 0.93);
			border-bottom: 1px solid rgba(31, 95, 63, 0.12);
			backdrop-filter: blur(8px);
		}

		.top-nav .brand {
			display: flex;
			align-items: center;
			gap: 0.65rem;
			font-weight: 900;
			color: var(--brand-forest);
		}

		.top-nav .brand img {
			width: 42px;
			height: 42px;
			border-radius: 10px;
			object-fit: cover;
			background: #fff;
		}

		.top-nav .links {
			display: flex;
			align-items: center;
			gap: 0.85rem;
			font-weight: 700;
			font-size: 0.93rem;
		}

		.top-nav .links a {
			padding: 0.45rem 0.75rem;
			border-radius: 999px;
		}

		.top-nav .links a:hover {
			background: var(--brand-mint);
		}

		.hero {
			position: relative;
			margin: 0;
			padding: 0;
		}

		.hero-copy {
			position: absolute;
			left: 50%;
			top: 50%;
			transform: translate(-50%, -50%);
			width: min(94vw, 980px);
			z-index: 6;
			background: linear-gradient(120deg, rgba(10, 30, 21, 0.72), rgba(10, 30, 21, 0.45));
			border-radius: 18px;
			padding: clamp(1rem, 2.4vw, 2rem);
			box-shadow: var(--card-shadow);
			animation: riseUp 0.8s ease both;
			backdrop-filter: blur(2px);
		}

		.hero-copy h1 {
			margin: 0;
			font-size: clamp(1.7rem, 4vw, 3rem);
			line-height: 1.1;
			color: #ffffff;
		}

		.hero-copy p {
			margin: 0.8rem 0 0;
			line-height: 1.68;
			color: #f1fbf5;
		}

		.kicker {
			display: inline-block;
			padding: 0.45rem 0.75rem;
			border-radius: 999px;
			font-size: 0.8rem;
			font-weight: 900;
			letter-spacing: 0.04em;
			background: rgba(255, 255, 255, 0.16);
			color: #fff;
			text-transform: uppercase;
		}

		.hero-actions {
			margin-top: 1rem;
			display: flex;
			gap: 0.7rem;
			flex-wrap: wrap;
		}

		.btn {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			gap: 0.45rem;
			padding: 0.7rem 1rem;
			border-radius: 999px;
			font-weight: 900;
			letter-spacing: 0.02em;
			border: none;
			cursor: pointer;
			transition: transform 0.18s ease, box-shadow 0.18s ease;
		}

		.btn:hover {
			transform: translateY(-2px);
		}

		.btn-primary {
			background: linear-gradient(120deg, var(--brand-forest), #2f8a5a);
			color: #fff;
			box-shadow: 0 10px 18px rgba(31, 95, 63, 0.28);
		}

		.btn-secondary {
			background: var(--brand-gold);
			color: #20221f;
			box-shadow: 0 10px 18px rgba(242, 181, 68, 0.3);
		}

		.slider-shell {
			position: relative;
			overflow: hidden;
			border-radius: 0;
			width: 100%;
			aspect-ratio: var(--slide-ratio, 16 / 9);
			height: auto;
			min-height: 260px;
			max-height: 76vh;
			box-shadow: none;
			animation: riseUp 0.95s ease both;
			background: #0b1110;
		}

		.slide {
			position: absolute;
			inset: 0;
			opacity: 0;
			transition: opacity 0.55s ease;
		}

		.slide.active {
			opacity: 1;
			z-index: 2;
		}

		.slide img {
			width: 100%;
			height: 100%;
			object-fit: contain;
			object-position: center center;
			image-rendering: auto;
		}

		.slide-caption {
			position: absolute;
			left: 50%;
			transform: translateX(-50%);
			width: min(92vw, 980px);
			bottom: 1.25rem;
			padding: 0.7rem 0.9rem;
			background: linear-gradient(90deg, rgba(0, 0, 0, 0.65), rgba(0, 0, 0, 0.25));
			color: #fff;
			font-weight: 800;
			border-radius: 10px;
			font-size: 0.95rem;
		}

		.slide-control {
			position: absolute;
			top: 50%;
			transform: translateY(-50%);
			width: 40px;
			height: 40px;
			border: none;
			border-radius: 999px;
			cursor: pointer;
			background: rgba(12, 35, 23, 0.68);
			color: #fff;
			font-size: 1.2rem;
			z-index: 4;
		}

		.slide-control:hover {
			background: rgba(12, 35, 23, 0.88);
		}

		.slide-control.prev {
			left: 0.7rem;
		}

		.slide-control.next {
			right: 0.7rem;
		}

		section {
			max-width: 1160px;
			margin: 1.6rem auto;
			padding: 0 1rem;
		}

		.block {
			background: #fff;
			border-radius: 18px;
			padding: 1.3rem;
			box-shadow: 0 8px 22px rgba(25, 39, 31, 0.09);
		}

		h2 {
			margin: 0 0 0.75rem;
			font-size: clamp(1.3rem, 3vw, 2rem);
			color: var(--brand-forest);
		}

		.offer-grid,
		.facility-grid,
		.news-grid,
		.gallery-grid {
			display: grid;
			grid-template-columns: repeat(3, minmax(0, 1fr));
			gap: 0.9rem;
		}

		.offer-card,
		.facility-card,
		.news-card {
			background: var(--brand-cream);
			padding: 0.95rem;
			border-radius: 12px;
			border: 1px solid rgba(31, 95, 63, 0.12);
		}

		.offer-card i,
		.facility-card i {
			font-size: 1.35rem;
			color: var(--brand-forest);
		}

		.gallery-grid button {
			padding: 0;
			border: none;
			background: transparent;
			cursor: pointer;
			position: relative;
			overflow: hidden;
			border-radius: 12px;
		}

		.gallery-grid img {
			width: 100%;
			height: 200px;
			object-fit: cover;
			display: block;
			transition: transform 0.35s ease;
		}

		.gallery-grid button:hover img {
			transform: scale(1.06);
		}

		.contact-grid {
			display: grid;
			grid-template-columns: 1fr 1fr;
			gap: 0.9rem;
		}

		.contact-form input,
		.contact-form textarea {
			width: 100%;
			padding: 0.75rem;
			border: 1px solid #cfdad1;
			border-radius: 10px;
			margin-bottom: 0.6rem;
			font: inherit;
		}

		.contact-form textarea {
			min-height: 120px;
			resize: vertical;
		}

		.map-wrap iframe {
			width: 100%;
			height: 100%;
			min-height: 240px;
			border: 0;
			border-radius: 12px;
		}

		.lightbox {
			position: fixed;
			inset: 0;
			z-index: 120;
			background: rgba(0, 0, 0, 0.82);
			display: none;
			align-items: center;
			justify-content: center;
			padding: 1rem;
		}

		.lightbox img {
			max-width: min(1100px, 96vw);
			max-height: 84vh;
			border-radius: 12px;
			box-shadow: 0 20px 45px rgba(0, 0, 0, 0.4);
		}

		.lightbox .close {
			position: absolute;
			right: 1.1rem;
			top: 0.8rem;
			width: 40px;
			height: 40px;
			border: none;
			border-radius: 999px;
			font-size: 1.3rem;
			background: rgba(255, 255, 255, 0.2);
			color: #fff;
			cursor: pointer;
		}

		footer {
			margin-top: 2rem;
			background: linear-gradient(120deg, #0f2e1f, #184d31);
			color: #f2f6f3;
			padding: 1.2rem 1rem 1.4rem;
		}

		.footer-wrap {
			max-width: 1160px;
			margin: 0 auto;
			display: flex;
			justify-content: space-between;
			align-items: center;
			gap: 1rem;
			flex-wrap: wrap;
		}

		.footer-links {
			display: flex;
			gap: 0.8rem;
			font-weight: 700;
			font-size: 0.92rem;
		}

		.notice {
			margin-top: 0.55rem;
			font-size: 0.85rem;
			color: #5a6a5f;
		}

		.pwa-actions {
			position: fixed;
			right: 14px;
			bottom: 14px;
			z-index: 80;
			display: flex;
			gap: 0.55rem;
			flex-wrap: wrap;
			justify-content: flex-end;
		}

		.pwa-actions .btn {
			padding: 0.62rem 0.9rem;
			font-size: 0.82rem;
		}

		@keyframes riseUp {
			from {
				opacity: 0;
				transform: translateY(14px);
			}
			to {
				opacity: 1;
				transform: translateY(0);
			}
		}

		@media (max-width: 980px) {
			.offer-grid,
			.facility-grid,
			.news-grid,
			.gallery-grid,
			.contact-grid {
				grid-template-columns: repeat(2, minmax(0, 1fr));
			}
		}

		@media (max-width: 680px) {
			.top-nav {
				flex-direction: column;
				align-items: flex-start;
			}

			.top-nav .links {
				flex-wrap: wrap;
			}

			.offer-grid,
			.facility-grid,
			.news-grid,
			.gallery-grid,
			.contact-grid {
				grid-template-columns: 1fr;
			}

			.slider-shell {
				min-height: 220px;
				max-height: 62vh;
			}

			.hero-copy {
				width: calc(100vw - 1.3rem);
				left: 0.65rem;
				top: auto;
				bottom: 4rem;
				transform: none;
			}

			.slide-caption {
				width: calc(100vw - 1.3rem);
			}
		}
	</style>
</head>
<body>
	<nav class="top-nav">
		<div class="brand">
			<img src="<?php echo htmlspecialchars($schoolLogo); ?>" alt="School logo">
			<span><?php echo htmlspecialchars($schoolName); ?></span>
		</div>
		<div class="links">
			<a href="#about">About</a>
			<a href="#offers">What We Offer</a>
			<a href="#gallery">Gallery</a>
			<a href="#contact">Contact</a>
			<a href="index.php">Login</a>
		</div>
	</nav>

	<header class="hero" id="home">
		<div class="slider-shell" id="mainSlider" aria-label="School showcase slider">
			<div class="hero-copy">
				<h1><?php echo htmlspecialchars($schoolName); ?></h1>
				<p><strong><?php echo htmlspecialchars($schoolMotto); ?></strong></p>
				<p><?php echo htmlspecialchars($schoolTagline); ?></p>
				<p><i class="bi bi-geo-alt-fill"></i> <?php echo htmlspecialchars($schoolLocation); ?></p>
				<div class="hero-actions">
					<a class="btn btn-primary" href="#contact"><i class="bi bi-person-plus"></i> Apply Now</a>
					<a class="btn btn-secondary" href="#contact"><i class="bi bi-telephone"></i> Contact Us</a>
				</div>
			</div>
			<?php foreach ($slides as $i => $slide): ?>
				<figure class="slide<?php echo $i === 0 ? ' active' : ''; ?>">
					<img src="<?php echo htmlspecialchars($slide['src']); ?>" alt="Showcase image <?php echo $i + 1; ?>">
					<figcaption class="slide-caption"><?php echo htmlspecialchars(trim((string)$slide['caption']) !== '' ? (string)$slide['caption'] : $captions[$i % count($captions)]); ?></figcaption>
				</figure>
			<?php endforeach; ?>
			<button type="button" class="slide-control prev" aria-label="Previous slide">&#10094;</button>
			<button type="button" class="slide-control next" aria-label="Next slide">&#10095;</button>
		</div>
	</header>

	<section id="about">
		<div class="block">
			<h2>About the School</h2>
			<p><?php echo htmlspecialchars($aboutText); ?></p>
			<p><strong>Vision:</strong> <?php echo htmlspecialchars($visionText); ?></p>
			<p><strong>Mission:</strong> <?php echo htmlspecialchars($missionText); ?></p>
			<p><strong>Core Values:</strong> <?php echo htmlspecialchars($coreValues); ?></p>
		</div>
	</section>

	<section id="offers">
		<div class="block">
			<h2>What the School Offers</h2>
			<div class="offer-grid">
				<?php foreach ($offers as $offer): ?>
					<div class="offer-card"><i class="bi bi-book"></i><h3><?php echo htmlspecialchars((string)$offer['title']); ?></h3><p><?php echo htmlspecialchars((string)$offer['description']); ?></p></div>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<section id="facilities">
		<div class="block">
			<h2>Facilities</h2>
			<div class="facility-grid">
				<?php foreach ($facilities as $facility): ?>
					<div class="facility-card"><i class="bi bi-building"></i><h3><?php echo htmlspecialchars((string)$facility['title']); ?></h3><p><?php echo htmlspecialchars((string)$facility['description']); ?></p></div>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<section id="news">
		<div class="block">
			<h2>News & Events</h2>
			<div class="news-grid">
				<?php foreach ($newsItems as $news): ?>
					<div class="news-card"><h3><?php echo htmlspecialchars((string)$news['title']); ?></h3><p><?php echo htmlspecialchars((string)$news['description']); ?></p></div>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<section id="gallery">
		<div class="block">
			<h2>Gallery</h2>
			<div class="gallery-grid">
				<?php foreach ($slides as $i => $slide): ?>
					<button type="button" class="gallery-item" data-full-src="<?php echo htmlspecialchars($slide['src']); ?>" aria-label="Open gallery image <?php echo $i + 1; ?>">
						<img src="<?php echo htmlspecialchars($slide['src']); ?>" alt="Gallery image <?php echo $i + 1; ?>">
					</button>
				<?php endforeach; ?>
			</div>
			<?php if (!$usesDbShowcase && count($galleryFiles) === 0): ?>
				<p class="notice">No database gallery images found yet. Upload photos in Admin &gt; System Settings &gt; Public Website Media.</p>
			<?php endif; ?>
		</div>
	</section>

	<section id="contact">
		<div class="block">
			<h2>Contact Us</h2>
			<div class="contact-grid">
				<div>
					<p><i class="bi bi-geo-alt"></i> <strong>Location:</strong> <?php echo htmlspecialchars($schoolLocation); ?></p>
					<p><a class="btn btn-secondary" href="<?php echo htmlspecialchars($schoolMapUrl); ?>" target="_blank" rel="noopener"><i class="bi bi-geo-alt-fill"></i> Open School Location</a></p>
					<p><i class="bi bi-telephone"></i> <strong>Phone:</strong> <?php echo htmlspecialchars($schoolPhone); ?></p>
					<p><i class="bi bi-envelope"></i> <strong>Email:</strong> <?php echo htmlspecialchars($schoolEmail); ?></p>
					<form class="contact-form" id="contactForm">
						<input type="text" name="name" placeholder="Your Name" required>
						<input type="email" name="email" placeholder="Your Email" required>
						<textarea name="message" placeholder="Write your message" required></textarea>
						<button type="submit" class="btn btn-primary">Send Message</button>
					</form>
				</div>
				<div class="map-wrap">
					<iframe title="School location map" loading="lazy" referrerpolicy="no-referrer-when-downgrade" src="https://maps.google.com/maps?q=Kiunduani%20Kibwezi%20West&t=&z=13&ie=UTF8&iwloc=&output=embed"></iframe>
				</div>
			</div>
		</div>
	</section>

	<div class="lightbox" id="lightbox" role="dialog" aria-modal="true" aria-label="Expanded gallery image">
		<button class="close" type="button" aria-label="Close image">&times;</button>
		<img src="" alt="Full gallery view" id="lightboxImage">
	</div>

	<div class="pwa-actions">
		<button id="installBtn" type="button" class="btn btn-primary" style="display:none;"><i class="bi bi-download"></i> Install App</button>
		<button id="notifyBtn" type="button" class="btn btn-secondary"><i class="bi bi-bell"></i> Enable Notifications</button>
	</div>

	<footer>
		<div class="footer-wrap">
			<div>
				<strong><?php echo htmlspecialchars($schoolName); ?></strong><br>
				<span><?php echo htmlspecialchars($schoolMotto); ?></span>
			</div>
			<div class="footer-links">
				<a href="#home">Home</a>
				<a href="#about">About</a>
				<a href="#gallery">Gallery</a>
				<a href="#contact">Contact</a>
			</div>
			<div>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($schoolName); ?>. All rights reserved.</div>
		</div>
	</footer>

	<script>
	(function () {
		var slider = document.getElementById('mainSlider');
		if (!slider) {
			return;
		}

		var slides = slider.querySelectorAll('.slide');
		var prevBtn = slider.querySelector('.slide-control.prev');
		var nextBtn = slider.querySelector('.slide-control.next');
		var index = 0;
		var timer = null;

		function setSliderRatio(imageEl) {
			if (!imageEl) {
				return;
			}
			var w = imageEl.naturalWidth || 0;
			var h = imageEl.naturalHeight || 0;
			if (w > 0 && h > 0) {
				slider.style.setProperty('--slide-ratio', w + ' / ' + h);
			}
		}

		function showSlide(newIndex) {
			if (!slides.length) {
				return;
			}
			if (newIndex < 0) {
				index = slides.length - 1;
			} else if (newIndex >= slides.length) {
				index = 0;
			} else {
				index = newIndex;
			}
			for (var i = 0; i < slides.length; i++) {
				slides[i].classList.remove('active');
			}
			slides[index].classList.add('active');
			var activeImage = slides[index].querySelector('img');
			if (activeImage) {
				if (activeImage.complete) {
					setSliderRatio(activeImage);
				} else {
					activeImage.addEventListener('load', function handleLoad() {
						setSliderRatio(activeImage);
						activeImage.removeEventListener('load', handleLoad);
					});
				}
			}
		}

		function restartAuto() {
			if (timer) {
				window.clearInterval(timer);
			}
			timer = window.setInterval(function () {
				showSlide(index + 1);
			}, 4000);
		}

		if (prevBtn) {
			prevBtn.addEventListener('click', function () {
				showSlide(index - 1);
				restartAuto();
			});
		}

		if (nextBtn) {
			nextBtn.addEventListener('click', function () {
				showSlide(index + 1);
				restartAuto();
			});
		}

		showSlide(0);
		restartAuto();
	})();

	(function () {
		var lightbox = document.getElementById('lightbox');
		var lightboxImage = document.getElementById('lightboxImage');
		if (!lightbox || !lightboxImage) {
			return;
		}

		var closeBtn = lightbox.querySelector('.close');
		var galleryButtons = document.querySelectorAll('.gallery-item');

		function closeLightbox() {
			lightbox.style.display = 'none';
			lightboxImage.src = '';
		}

		for (var i = 0; i < galleryButtons.length; i++) {
			galleryButtons[i].addEventListener('click', function () {
				var fullSrc = this.getAttribute('data-full-src');
				if (!fullSrc) {
					return;
				}
				lightboxImage.src = fullSrc;
				lightbox.style.display = 'flex';
			});
		}

		if (closeBtn) {
			closeBtn.addEventListener('click', closeLightbox);
		}

		lightbox.addEventListener('click', function (event) {
			if (event.target === lightbox) {
				closeLightbox();
			}
		});

		document.addEventListener('keydown', function (event) {
			if (event.key === 'Escape') {
				closeLightbox();
			}
		});
	})();

	(function () {
		var form = document.getElementById('contactForm');
		if (!form) {
			return;
		}
		form.addEventListener('submit', function (event) {
			event.preventDefault();
			alert('Thank you for contacting us. We will reach out to you soon.');
			form.reset();
		});
	})();

	(function () {
		if (!('serviceWorker' in navigator)) {
			return;
		}
		navigator.serviceWorker.register('service-worker.js').catch(function () {
			return null;
		});

		var deferredPrompt = null;
		var installBtn = document.getElementById('installBtn');
		window.addEventListener('beforeinstallprompt', function (e) {
			e.preventDefault();
			deferredPrompt = e;
			if (installBtn) {
				installBtn.style.display = 'inline-flex';
			}
		});

		if (installBtn) {
			installBtn.addEventListener('click', function () {
				if (!deferredPrompt) {
					return;
				}
				deferredPrompt.prompt();
				deferredPrompt = null;
				installBtn.style.display = 'none';
			});
		}

		var notifyBtn = document.getElementById('notifyBtn');
		if (notifyBtn) {
			notifyBtn.addEventListener('click', function () {
				if (!('Notification' in window)) {
					alert('Notifications are not supported in this browser.');
					return;
				}
				Notification.requestPermission().then(function (permission) {
					if (permission !== 'granted') {
						return;
					}
					navigator.serviceWorker.ready.then(function (registration) {
						registration.showNotification('Kyandulu Primary School', {
							body: 'Welcome! Stay updated with school news and events.',
							icon: 'images/pwa/icon-192.png'
						});
					});
				});
			});
		}
	})();
	</script>
</body>
</html>
