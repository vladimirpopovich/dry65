<?php get_header(); ?>
<main class="page-enter">
  <section class="bg-paper2 section-sm" style="padding-top:clamp(24px,3vw,40px);padding-bottom:clamp(20px,2.5vw,32px);">
    <div class="wrap">
      <?php if (have_posts()): while (have_posts()): the_post(); ?>
      <h1 class="display caps" style="font-size:clamp(30px,4.2vw,52px);max-width:28ch;line-height:1.0;"><?php the_title(); ?></h1>
      <?php endwhile; endif; ?>
    </div>
  </section>
  <section class="section">
    <div class="wrap" style="max-width:800px;">
      <?php if (have_posts()): while (have_posts()): the_post(); ?>
      <div class="entry-content"><?php the_content(); ?></div>
      <?php endwhile; endif; ?>
    </div>
  </section>
</main>
<?php get_footer(); ?>
