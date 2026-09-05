<?php
/* ============================================================
   Dry65 — engleski prevodi SADRŽAJA USLUGA (CPT dry65_service)
   ------------------------------------------------------------
   Sadržaj usluga živi u bazi (ne deploy-uje se sa kodom), pa EN
   prevode držimo ovde, po slug-u. Helperi dry65_svc + tree citaju:
   ACF polje `{polje}_en`  ->  ova mapa  ->  srpski original (fallback).

   Polja po usluzi:
     title    — naslov (post_title)
     intro    — duži uvodni pasus na /usluge listi (SR: excerpt ili short)
     short    — kraći opis (hero na strani usluge)
     content  — pun članak (post_content), za pojedinačne stilove
     point_1..3 — čipovi/stavke
   Prazno / bez ključa = prikazuje se srpski.
   ============================================================ */

return [

  /* ---------- KATEGORIJE ---------- */
  'feniranje-na-cetke' => [
    'title' => 'Round-brush blow-dry',
    'intro' => 'A round-brush blow-dry is one of the most beautiful ways to give hair fullness, natural shape and healthy shine. At Dry65 we use this technique to bring out the beauty of your hair, creating a style that looks groomed, airy and elegant. Whatever your hair length or type, the result is a natural look that lasts and fits any occasion.',
    'short' => 'A round-brush blow-dry is one of the most beautiful ways to give hair fullness, natural shape and healthy shine. At Dry65 we use this technique to bring out the beauty of your hair, creating a style that looks groomed, airy and elegant. Whatever your hair length or type, the result is a natural look that lasts and fits any occasion.',
    'point_1' => 'Hair wash',
    'point_2' => 'Blow-dry by length',
    'point_3' => 'No appointments',
  ],
  'stilizovanje' => [
    'title' => 'Hair styling',
    'intro' => 'Styling means shaping with hot tools (flat iron, wand, curler) after drying. The result is more defined and holds much longer than a blow-dry, ideal for nights out, celebrations and special occasions. It works best on finer hair.',
    'short' => 'Blow-dry into waves, curls, sleek or volume. A look for every occasion.',
    'point_1' => 'Waves and curls',
    'point_2' => 'Sleek and straight',
    'point_3' => 'Volume for any occasion',
  ],
  'nega' => [
    'title' => 'Care & maintenance',
    'intro' => 'Deep care and treatments that restore shine, strength and hydration to your hair: hair infusion, masks, steam station and ritual care. Included with monthly packages.',
    'short' => 'Hair care treatments that make your blow-dry last and keep hair healthy.',
    'point_1' => 'Deep hydration',
    'point_2' => 'Care for coloured and grey hair',
    'point_3' => 'A gift with packages',
  ],

  /* ---------- FENIRANJE — pojedinačni stilovi ---------- */
  'feniranje-na-ravno' => [
    'title' => 'Straight blow-dry',
    'short' => 'A classic blow-dry that never goes out of style. Perfectly straight hair, natural shine and a finish tailored to your wishes make this look an ideal choice for every day and for special occasions.',
  ],
  'feniranje-na-talase' => [
    'title' => 'Wavy blow-dry',
    'short' => 'Natural waves that give movement, fullness and lightness to any style. Whether you like a subtle or a more pronounced look, a wavy blow-dry delivers an elegant, modern style that looks natural on any occasion.',
    'point_1' => 'Natural look',
    'point_2' => 'Volume and movement',
    'point_3' => 'For every hair type',
  ],
  'feniranje-na-lokne' => [
    'title' => 'Curly blow-dry',
    'short' => 'Rich, long-lasting curls that bring volume, movement and a touch of glamour to any style. From soft and romantic to more defined curls, the blow-dry adapts to your style and the occasion so your hair looks luxurious and groomed.',
    'point_1' => 'Rich volume',
    'point_2' => 'Long-lasting curls',
    'point_3' => 'For special occasions',
  ],
  'feniranje-na-volumen' => [
    'title' => 'Volume blow-dry',
    'short' => 'More fullness, lifted roots and hair that looks thicker, livelier and fuller. A volume blow-dry adapts to every hair type and can be combined with a straight blow-dry, waves or curls for a natural, long-lasting result.',
    'point_1' => 'More fullness',
    'point_2' => 'Lifted roots',
    'point_3' => 'For every hair type',
  ],

  /* ---------- STILIZOVANJE — stilovi (strane još u pripremi) ---------- */
  'ravna-kosa-peglom' => [
    'title' => 'Flat-iron straight hair',
    'excerpt' => 'Glass-smooth, shiny, perfectly straight hair with a flat iron. A sleek look that holds even in humid weather.',
  ],
  'talasi-peglom' => [
    'title' => 'Flat-iron waves',
    'excerpt' => 'Defined waves made with a flat iron, more pronounced and longer-lasting than a blow-dry. For a polished, elegant look.',
  ],
  'lokne-figarom' => [
    'title' => 'Wand curls',
    'excerpt' => 'Full, defined curls with a wand or curler. A glamorous look that holds all night.',
  ],
  'hollywood-talasi' => [
    'title' => 'Hollywood waves',
    'excerpt' => 'Smooth, uniform retro waves, red-carpet style. For weddings, celebrations and big nights out.',
  ],
  'beach-waves' => [
    'title' => 'Beach waves',
    'excerpt' => 'Relaxed, softly tousled summer waves. An easy, modern look that seems natural yet polished.',
  ],

  /* ---------- NEGA — tretmani (strane još u pripremi) ---------- */
  'infuzija-kose' => [
    'title' => 'Infusion',
    'excerpt' => 'A deep infusion of nourishing ingredients that rebuilds hair from within, for shine, strength and softness.',
  ],
  'maska-za-kosu' => [
    'title' => 'Mask',
    'excerpt' => 'An intensive mask that hydrates, nourishes and restores elasticity to damaged and dry hair.',
  ],
  'booster-za-kosu' => [
    'title' => 'Booster',
    'excerpt' => 'A concentrated booster treatment for extra strength, shine and protection.',
  ],
  'parna-stanica' => [
    'title' => 'Steam station',
    'excerpt' => 'A steam treatment that opens the hair cuticle and boosts the effect of care, for a deep, long-lasting result.',
  ],

];
