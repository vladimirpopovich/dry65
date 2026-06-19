<?php get_header(); ?>

<main class="page-enter">

<section class="bg-paper2 section-sm" style="padding-top:clamp(24px,3vw,40px);padding-bottom:clamp(20px,2.5vw,32px);">
  <div class="wrap">
    <?php if (have_posts()): while (have_posts()): the_post(); ?>
    <div style="max-width:800px;">
      <a href="<?php echo esc_url(get_post_type_archive_link('post')); ?>" class="textlink" style="font-size:13px;margin-bottom:24px;display:inline-flex;">← Blog</a>
      <?php
      $cats = get_the_category();
      if ($cats) echo '<span class="chip" style="margin-bottom:18px;display:inline-flex;">' . esc_html($cats[0]->name) . '</span>';
      ?>
      <h1 class="display caps" style="font-size:clamp(38px,5.5vw,72px);margin-top:10px;line-height:1.05;">
        <?php the_title(); ?>
      </h1>
      <div class="row" style="gap:20px;margin-top:24px;align-items:center;">
        <span class="muted" style="font-size:14px;"><?php echo get_the_date(); ?></span>
        <span class="muted" style="font-size:14px;">·</span>
        <span class="muted" style="font-size:14px;">
          <?php echo dry65_reading_time(); ?> min čitanja
        </span>
      </div>
    </div>
    <?php endwhile; endif; ?>
  </div>
</section>

<section class="section">
  <div class="wrap" style="max-width:760px;">
    <?php if (have_posts()): while (have_posts()): the_post(); ?>

    <?php if (has_post_thumbnail()): ?>
    <div style="aspect-ratio:16/9;border-radius:var(--radius-lg);overflow:hidden;margin-bottom:clamp(32px,5vw,56px);">
      <?php the_post_thumbnail('full', ['style' => 'width:100%;height:100%;object-fit:cover;display:block;']); ?>
    </div>
    <?php endif; ?>

    <article class="entry-content blog-body">
      <?php the_content(); ?>
    </article>

    <!-- Share buttons -->
    <?php echo dry65_share_buttons(); ?>

    <!-- CTA box -->
    <?php echo dry65_post_cta(); ?>

    <?php endwhile; endif; ?>

    <!-- Related posts -->
    <?php
    $related = dry65_related_posts(null, 3);
    if (!empty($related)): ?>
    <section style="margin-top:80px;">
      <h3 class="display" style="font-size:clamp(26px,3.4vw,38px);margin:0 0 32px;text-align:center;">
        Možda će ti se svideti
      </h3>
      <div class="grid cols-3" style="gap:24px;">
        <?php foreach ($related as $r): setup_postdata($GLOBALS['post'] = $r); ?>
        <article class="card hover" style="display:flex;flex-direction:column;">
          <?php if (has_post_thumbnail($r->ID)): ?>
          <div style="aspect-ratio:16/9;overflow:hidden;">
            <?php echo get_the_post_thumbnail($r->ID, 'medium_large', ['style' => 'width:100%;height:100%;object-fit:cover;display:block;']); ?>
          </div>
          <?php else: ?>
          <div class="ph" style="aspect-ratio:16/9;border-radius:0;"></div>
          <?php endif; ?>
          <div style="padding:22px 22px 26px;flex:1;display:flex;flex-direction:column;">
            <h4 class="display" style="font-size:20px;margin:0 0 8px;flex:1;">
              <a href="<?php echo esc_url(get_permalink($r->ID)); ?>"><?php echo esc_html($r->post_title); ?></a>
            </h4>
            <span class="mono" style="font-size:12px;color:var(--muted);"><?php echo get_the_date('', $r->ID); ?></span>
          </div>
        </article>
        <?php endforeach; wp_reset_postdata(); ?>
      </div>
    </section>
    <?php endif; ?>
  </div>
</section>

</main>

<?php get_footer(); ?>
