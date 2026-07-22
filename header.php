<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo('charset'); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php wp_head(); // Yoast SEO dodaje meta description ovde ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<style>
  .nav-inner { position: relative; }
  /* Chip — desktop: poslednji element u meniju (skroz desno, u toku) */
  .nav-live {
    display: inline-flex; align-items: center; gap: 8px;
    font-family: var(--font-sans); font-size: 14px; font-weight: 600;
    letter-spacing: 0.02em; color: var(--ink); text-decoration: none; white-space: nowrap;
    border: 1px solid rgba(17,28,29,0.16); border-radius: 999px;
    padding: 7px 15px; background: #fff; transition: border-color .2s, background .2s;
  }
  .nav-live:hover { border-color: rgba(17,28,29,0.4); }
  .nav-live-dot {
    width: 9px; height: 9px; border-radius: 50%; background: #2f9e44; flex-shrink: 0;
    box-shadow: 0 0 0 0 rgba(47,158,68,0.5);
    animation: navLivePulse 2s ease-out infinite;
  }
  @keyframes navLivePulse {
    0%   { box-shadow: 0 0 0 0 rgba(47,158,68,0.5); }
    70%  { box-shadow: 0 0 0 6px rgba(47,158,68,0); }
    100% { box-shadow: 0 0 0 0 rgba(47,158,68,0); }
  }
  @media (prefers-reduced-motion: reduce) { .nav-live-dot { animation: none; } }
  /* Mobilni: centrirano između logoa i hamburgera */
  @media (max-width: 1080px) {
    .nav-live { position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%); }
  }
</style>

<header class="nav" id="site-header">
  <div class="wrap nav-inner">
    <a class="brand" href="<?php echo esc_url(home_url('/')); ?>" aria-label="Dry65, Blowout Hair Bar">
      <img class="brand-img" src="<?php echo get_template_directory_uri(); ?>/assets/logo.svg" alt="Dry65, Blowout Hair Bar" width="140" height="42">
    </a>

    <nav class="nav-links" aria-label="Glavna navigacija">
      <?php dry65_nav_links(); ?>
    </nav>

    <a class="nav-live" href="<?php echo esc_url(home_url('/live/')); ?>" aria-label="Dry65 — status uživo">
      <span class="nav-live-dot" aria-hidden="true"></span>dry65 live
    </a>

    <div class="nav-tools">
      <button class="nav-burger" id="nav-burger" aria-label="Otvori meni" aria-expanded="false" aria-controls="mobile-menu">
        <span></span>
        <span></span>
        <span></span>
      </button>
    </div>
  </div>

  <div class="mobile-menu" id="mobile-menu" aria-hidden="true" inert>
    <?php dry65_nav_links(true); ?>
  </div>
</header>
