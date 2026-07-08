<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo('charset'); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php wp_head(); // Yoast SEO dodaje meta description ovde ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header class="nav" id="site-header">
  <div class="wrap nav-inner">
    <a class="brand" href="<?php echo esc_url(home_url('/')); ?>" aria-label="Dry65, Blowout Hair Bar">
      <img class="brand-img" src="<?php echo get_template_directory_uri(); ?>/assets/logo.svg" alt="Dry65, Blowout Hair Bar" width="140" height="42">
    </a>

    <nav class="nav-links" aria-label="Glavna navigacija">
      <?php dry65_nav_links(); ?>
    </nav>

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
