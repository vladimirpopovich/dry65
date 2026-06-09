<?php get_header(); ?>
<main class="page-enter">
  <section class="section">
    <div class="wrap">
      <?php if (have_posts()): while (have_posts()): the_post(); ?>
      <h2 class="display"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
      <div><?php the_excerpt(); ?></div>
      <?php endwhile; endif; ?>
    </div>
  </section>
</main>
<?php get_footer(); ?>
