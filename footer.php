<?php
$biz = dry65_biz();
$nav = dry65_nav();
$col1 = array_slice($nav, 0, 4);
$col2 = array_slice($nav, 4);
$tpl  = get_template_directory_uri();
?>

<footer class="footer">
  <div class="wrap">
    <div class="footer-grid">

      <div>
        <a class="brand" href="<?php echo esc_url(home_url('/')); ?>" aria-label="Dry65, Blowout Hair Bar">
          <img class="brand-img" src="<?php echo $tpl; ?>/assets/logo-light.svg" alt="Dry65, Blowout Hair Bar" width="140" height="52">
        </a>
        <p style="margin-top:18px;max-width:260px;color:rgba(242,225,190,0.7);font-size:15px;">
          Blowout hair bar bez zakazivanja. Radimo jednu stvar, feniramo. I to savršeno.
        </p>
        <div style="margin-top:20px;">
          <a href="<?php echo esc_url($biz['instagram_url']); ?>" target="_blank" rel="noopener" class="btn btn-ghost-light">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:8px;vertical-align:-3px;"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line></svg>
            Prati nas <span class="arrow">→</span>
          </a>
        </div>
      </div>

      <div>
        <h3 class="footer-h">Stranice</h3>
        <ul>
          <?php foreach ($col1 as $item):
            $url = get_permalink(get_page_by_path($item['slug'])) ?: home_url('/' . $item['slug'] . '/'); ?>
            <li><a href="<?php echo esc_url($url); ?>"><?php echo esc_html($item['label']); ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>

      <div>
        <h3 class="footer-h">Više</h3>
        <ul>
          <?php foreach ($col2 as $item):
            $url = get_permalink(get_page_by_path($item['slug'])) ?: home_url('/' . $item['slug'] . '/'); ?>
            <li><a href="<?php echo esc_url($url); ?>"><?php echo esc_html($item['label']); ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>

      <div>
        <h3 class="footer-h">Kontakt</h3>
        <ul>
          <li><?php echo esc_html($biz['address']); ?></li>
          <li><a href="tel:<?php echo esc_attr($biz['phone']); ?>"><?php echo esc_html($biz['phone_display']); ?></a></li>
          <li><a href="mailto:<?php echo esc_attr($biz['email']); ?>"><?php echo esc_html($biz['email']); ?></a></li>
        </ul>
      </div>

    </div>

    <div class="row" style="justify-content:space-between;flex-wrap:wrap;gap:14px;margin-top:52px;padding-top:24px;border-top:1px solid rgba(242,225,190,0.18);font-size:13px;color:rgba(242,225,190,0.6);">
      <span>© <?php echo date('Y'); ?> Dry65. Sva prava zadržana.</span>
      <span class="row" style="gap:20px;">
        <a href="#">Politika privatnosti</a>
        <a href="#">Uslovi korišćenja</a>
      </span>
    </div>
  </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
