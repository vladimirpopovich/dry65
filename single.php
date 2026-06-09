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
      <div class="row" style="gap:20px;margin-top:20px;">
        <span class="muted" style="font-size:14px;"><?php echo get_the_date(); ?></span>
      </div>
    </div>
    <?php endwhile; endif; ?>
  </div>
</section>

<section class="section">
  <div class="wrap" style="max-width:800px;">
    <?php if (have_posts()): while (have_posts()): the_post(); ?>
    <?php if (has_post_thumbnail()): ?>
    <div style="aspect-ratio:16/9;border-radius:var(--radius-lg);overflow:hidden;margin-bottom:clamp(32px,5vw,56px);">
      <?php the_post_thumbnail('full', ['style' => 'width:100%;height:100%;object-fit:cover;display:block;']); ?>
    </div>
    <?php endif; ?>
    <div class="entry-content display" style="font-size:clamp(18px,2vw,22px);line-height:1.7;">
      <?php the_content(); ?>
    </div>
    <?php endwhile; endif; ?>
  </div>
</section>

</main>

<?php get_footer(); ?>
