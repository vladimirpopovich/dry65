<?php
/*
Template Name: Ambijent
*/
get_header();
$gallery = dry65_gallery();
$biz     = dry65_biz();
$tpl     = get_template_directory_uri();
$ratios  = ['3/4','1/1','4/5','4/5','3/4','4/5','1/1','3/4','4/5'];
?>

<main class="page-enter">

<section class="bg-paper2 section-sm" style="padding-top:clamp(24px,3vw,40px);padding-bottom:clamp(20px,2.5vw,32px);">
  <div class="wrap">
    <span class="script" style="font-size:clamp(28px,3.6vw,44px);display:block;margin-bottom:4px;">Ambijent</span>
    <h1 class="display caps" style="font-size:clamp(30px,4.2vw,52px);margin-top:4px;max-width:32ch;line-height:1.0;letter-spacing:0.01em;">
      Ambijent frizerskog salona Dry65, Novi Beograd
    </h1>
    <p class="lead" style="margin-top:26px;max-width:680px;">
      Ambijent osmišljen tako da se osećate prijatno. Imate svoj prostor, svoje vreme i komfor koji Vam je potreban da izađete sa najboljom frizurom.
    </p>
  </div>
</section>

<section class="section">
  <div class="wrap">
    <div style="column-count:3;column-gap:clamp(16px,2vw,26px);" class="masonry">
      <?php foreach ($gallery as $i => $g):
        $ratio = $ratios[$i % count($ratios)];
      ?>
      <div style="break-inside:avoid;margin-bottom:clamp(16px,2vw,26px);">
        <div class="reveal" data-delay="<?php echo ($i % 3) * 60; ?>">
          <div style="aspect-ratio:<?php echo $ratio; ?>;border-radius:var(--radius-lg);overflow:hidden;">
            <?php echo dry65_picture($g['img'], $g['tag'], [
              'loading' => 'lazy',
              'style'   => 'width:100%;height:100%;object-fit:cover;display:block;',
            ]); ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="center" style="margin-top:44px;">
      <a href="<?php echo esc_url($biz['instagram_url']); ?>" target="_blank" rel="noopener" class="btn btn-primary">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:8px;vertical-align:-3px;"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line></svg>
        Prati nas · <?php echo esc_html($biz['instagram']); ?> <span class="arrow">→</span>
      </a>
    </div>
  </div>
</section>

</main>

<?php get_footer(); ?>
