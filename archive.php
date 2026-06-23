<?php get_header(); ?>

<main class="page-enter">

<section class="bg-paper2 section-sm" style="padding-top:clamp(24px,3vw,40px);padding-bottom:clamp(20px,2.5vw,32px);">
  <div class="wrap">
    <span class="script" style="font-size:clamp(28px,3.6vw,44px);display:block;margin-bottom:4px;">Blog</span>
    <h1 class="display caps" style="font-size:clamp(30px,4.2vw,52px);margin-top:4px;max-width:28ch;line-height:1.0;letter-spacing:0.01em;">
      Saveti za kosu i feniranje
    </h1>
    <p class="lead" style="margin-top:26px;max-width:620px;">
      Mali vodiči o nezi, feniranju i tome kako da tvoja kosa izgleda i bude zdrava duže.
    </p>
  </div>
</section>

<section class="section">
  <div class="wrap">
    <?php if (have_posts()): ?>
    <div class="grid cols-3">
      <?php while (have_posts()): the_post(); ?>
      <article class="reveal card hover" style="display:flex;flex-direction:column;">
        <?php if (has_post_thumbnail()): ?>
        <div style="aspect-ratio:16/9;overflow:hidden;">
          <?php the_post_thumbnail('large', ['style' => 'width:100%;height:100%;object-fit:cover;display:block;']); ?>
        </div>
        <?php else: ?>
        <div class="ph" style="aspect-ratio:16/9;border-radius:0;">
          <span class="ph-tag"><?php the_title(); ?></span>
        </div>
        <?php endif; ?>
        <div style="padding:26px 26px 30px;flex:1;display:flex;flex-direction:column;">
          <div class="row" style="gap:12px;margin-bottom:14px;">
            <span class="chip"><?php echo get_the_category_list(', ') ?: 'Saveti'; ?></span>
            <span class="mono" style="font-size:12px;color:var(--muted);"><?php echo get_the_date(); ?></span>
          </div>
          <h2 class="display" style="font-size:28px;margin-top:0;flex:1;">
            <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
          </h2>
          <div style="margin-top:20px;">
            <a href="<?php the_permalink(); ?>" class="textlink">Pročitaj više <span>→</span></a>
          </div>
        </div>
      </article>
      <?php endwhile; ?>
    </div>
    <div style="margin-top:clamp(40px,5vw,60px);">
      <?php the_posts_pagination(['prev_text' => '← Stariji', 'next_text' => 'Noviji →']); ?>
    </div>
    <?php else: ?>
    <div class="center" style="padding:80px 0;">
      <p class="muted" style="font-size:15px;">Više tekstova uskoro.</p>
    </div>
    <?php endif; ?>
  </div>
</section>

</main>

<?php get_footer(); ?>
