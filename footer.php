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
        <div class="row" style="margin-top:20px;gap:10px;">
          <a href="<?php echo esc_url($biz['instagram_url']); ?>" target="_blank" rel="noopener" aria-label="Instagram — dry65belgrade" title="Instagram"
             style="display:inline-flex;align-items:center;justify-content:center;width:42px;height:42px;border-radius:50%;border:1px solid rgba(242,225,190,0.28);color:rgba(242,225,190,0.85);transition:background .2s, border-color .2s;">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line></svg>
          </a>
          <a href="<?php echo esc_url($biz['tiktok_url']); ?>" target="_blank" rel="noopener" aria-label="TikTok — dry65belgrade" title="TikTok"
             style="display:inline-flex;align-items:center;justify-content:center;width:42px;height:42px;border-radius:50%;border:1px solid rgba(242,225,190,0.28);color:rgba(242,225,190,0.85);transition:background .2s, border-color .2s;">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64 2.93 2.93 0 0 1 .88.13V9.4a6.84 6.84 0 0 0-1-.05A6.33 6.33 0 0 0 5.8 20.1a6.34 6.34 0 0 0 10.86-4.43V8.94a8.16 8.16 0 0 0 4.77 1.52V7A4.85 4.85 0 0 1 19.59 6.69Z"/></svg>
          </a>
          <a href="<?php echo esc_url($biz['youtube_url']); ?>" target="_blank" rel="noopener" aria-label="YouTube — dry65belgrade" title="YouTube"
             style="display:inline-flex;align-items:center;justify-content:center;width:42px;height:42px;border-radius:50%;border:1px solid rgba(242,225,190,0.28);color:rgba(242,225,190,0.85);transition:background .2s, border-color .2s;">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
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
          <?php $faq_page = get_page_by_path('faq'); if ($faq_page): ?>
            <li><a href="<?php echo esc_url(get_permalink($faq_page)); ?>">Česta pitanja</a></li>
          <?php endif; ?>
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
        <?php
        $privacy = get_page_by_path('politika-privatnosti');
        $terms   = get_page_by_path('uslovi-koriscenja');
        ?>
        <?php if ($privacy): ?>
          <a href="<?php echo esc_url(get_permalink($privacy)); ?>">Politika privatnosti</a>
        <?php endif; ?>
        <?php if ($terms): ?>
          <a href="<?php echo esc_url(get_permalink($terms)); ?>">Uslovi korišćenja</a>
        <?php endif; ?>
      </span>
    </div>
  </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
