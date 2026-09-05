<?php
/* ============================================================
   Dry65 — /karijera/asistent  (skriveni oglas za asistenta)
   ------------------------------------------------------------
   Odvojena strana, NIJE indeksirana (noindex) i nije u meniju,
   deli se samo direktnim linkom (npr. omladinska zadruga, IG).
   Radi preko rewrite pravila, ne treba WP strana u adminu.
   ============================================================ */

if (!defined('ABSPATH')) exit;

add_action('init', function () {
    add_rewrite_rule('^karijera/asistent/?$', 'index.php?dry65_asistent=1', 'top');
    if (get_option('dry65_asistent_rw_v') !== '1') {
        flush_rewrite_rules(false);
        update_option('dry65_asistent_rw_v', '1');
    }
});
add_filter('query_vars', function ($v) { $v[] = 'dry65_asistent'; return $v; });

/* Noindex — ne sme u pretragu (deli se samo linkom). */
add_action('wp_head', function () {
    if (get_query_var('dry65_asistent')) echo '<meta name="robots" content="noindex, nofollow">' . "\n";
}, 1);

add_action('template_redirect', function () {
    if (!get_query_var('dry65_asistent')) return;

    $biz   = function_exists('dry65_biz') ? dry65_biz() : ['email' => 'office@dry65.com', 'instagram_url' => '#'];
    $email = $biz['email'] ?? 'office@dry65.com';
    $mailto = 'mailto:' . rawurlencode($email) . '?subject=' . rawurlencode('Prijava za asistenta u Dry65');

    $duties = [
        'Pranje i pripremu kose',
        'Pomoć blowout specijalistima',
        'Pripremu klijenata za tretman',
        'Održavanje urednosti radnog prostora',
        'Rad u brzom, ali prijatnom timu',
    ];
    $perks = [
        ['💰', 'Plaćen rad nakon obuke'],
        ['🎓', 'Obuku od naših iskusnih kolega'],
        ['⏰', 'Fleksibilno angažovanje'],
        ['🤝', 'Mogućnost rada preko omladinske zadruge'],
        ['💇‍♀️', 'Iskustvo u modernom walk-in salonu'],
        ['📈', 'Ako se pokažeš, mogućnost da naučiš još više i napreduješ'],
    ];

    get_header();
    ?>
    <main class="page-enter">

    <section class="bg-paper2 section-sm" style="padding-top:clamp(24px,3vw,44px);padding-bottom:clamp(20px,2.5vw,32px);">
      <div class="wrap" style="max-width:760px;">
        <div style="display:inline-flex;align-items:center;gap:8px;background:var(--cream);color:var(--ink);border:1px solid var(--cream-deep);border-radius:999px;padding:5px 12px;font-size:11px;font-weight:600;letter-spacing:0.14em;text-transform:uppercase;margin-bottom:16px;">
          <span style="width:6px;height:6px;background:#2f9e44;border-radius:50%;"></span> Otvorena pozicija
        </div>
        <h1 class="display" style="font-size:clamp(28px,4vw,46px);margin:0;line-height:1.1;letter-spacing:0.005em;">
          Tražimo asistenta u Dry65, ne moraš da imaš iskustvo!
        </h1>
        <div class="lead" style="margin-top:22px;display:flex;flex-direction:column;gap:14px;">
          <p style="margin:0;">Voliš rad sa ljudima, odgovorna si osoba i želiš da zaradiš sa strane, a usput naučiš nešto novo?</p>
          <p style="margin:0;">U Dry65 Blowout Hair Bar tražimo osobu za pomoć timu u salonu.</p>
          <p style="margin:0;">Ako već imaš iskustva sa pranjem kose, radom u salonu ili sličnim poslovima, super, ali nije uslov. Najvažnije nam je da si pouzdana, odgovorna osoba i spremna da naučiš.</p>
        </div>
      </div>
    </section>

    <section class="section" style="padding-top:clamp(24px,3vw,36px);">
      <div class="wrap" style="max-width:760px;">
        <article style="background:var(--paper);border:1px solid var(--sage-line);border-radius:var(--radius-lg);padding:clamp(28px,4vw,48px);">

          <div class="karijera-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:clamp(24px,3vw,44px);">
            <div>
              <h2 class="mono" style="color:var(--muted);font-size:13px;text-transform:uppercase;letter-spacing:0.14em;margin:0 0 16px;">Šta ćeš raditi?</h2>
              <ul style="margin:0;padding:0;list-style:none;">
                <?php foreach ($duties as $d): ?>
                <li style="display:flex;gap:10px;padding:8px 0;line-height:1.55;">
                  <span style="color:var(--clay);flex-shrink:0;">→</span>
                  <span><?php echo esc_html($d); ?></span>
                </li>
                <?php endforeach; ?>
              </ul>
            </div>
            <div>
              <h2 class="mono" style="color:var(--muted);font-size:13px;text-transform:uppercase;letter-spacing:0.14em;margin:0 0 16px;">Šta dobijaš?</h2>
              <ul style="margin:0;padding:0;list-style:none;">
                <?php foreach ($perks as $pk): ?>
                <li style="display:flex;gap:10px;padding:8px 0;line-height:1.55;">
                  <span style="flex-shrink:0;"><?php echo $pk[0]; ?></span>
                  <span><?php echo esc_html($pk[1]); ?></span>
                </li>
                <?php endforeach; ?>
              </ul>
            </div>
          </div>

          <div style="margin-top:clamp(24px,3vw,34px);padding-top:24px;border-top:1px solid var(--sage-line);">
            <p style="margin:0 0 18px;max-width:640px;color:var(--ink-soft);line-height:1.6;">
              Ako tražiš posao koji možeš da uklopiš uz fakultet ili druge obaveze, možda si baš ti osoba koju tražimo.
            </p>
            <a href="<?php echo esc_url($mailto); ?>" class="btn btn-dark">
              📩 Pošalji CV na <?php echo esc_html($email); ?> <span class="arrow">→</span>
            </a>
            <p class="muted" style="margin-top:18px;max-width:640px;font-size:14.5px;line-height:1.6;">
              U CV-ju ne moraš da imaš iskustvo u frizerskoj industriji, zanima nas ko si, šta si do sada radila i kada možeš da radiš.
            </p>
            <p style="margin-top:16px;font-weight:600;font-size:16px;">Vidimo se u Dry65.</p>
          </div>

        </article>
      </div>
    </section>

    </main>
    <?php
    get_footer();
    exit;
});
