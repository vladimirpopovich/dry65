<?php
/* ============================================================
   Dry65 — engleski prevodi (EN)
   ------------------------------------------------------------
   Kljuc = srpski original (za t()) ILI kratak kod (za tk()).
   Vrednost = engleski tekst. Ako kljuca nema, prikazuje se srpski
   original (fallback), pa je bezbedno dodavati postepeno.

   NAPOMENA: ovaj fajl se popunjava po fazama. Prazne vrednosti
   ('') znace "jos nije prevedeno" -> prikazace se srpski original.
   ============================================================ */

return [

    /* ---- Navigacija (meni) ---- */
    'O nama'    => 'About',
    'Usluge'    => 'Services',
    'Cenovnik'  => 'Pricing',
    'Paketi'    => 'Packages',
    'Ambijent'  => 'Ambience',
    'Karijera'  => 'Careers',
    'Kontakt'   => 'Contact',
    'Blog'      => 'Blog',

    /* ---- UI (header/opste) ---- */
    'Otvori meni'  => 'Open menu',
    'Zatvori meni' => 'Close menu',

    /* ============================================================
       FAZA 2+ : ovde ce doci prevodi stranica (front-page, page-*,
       usluge, footer...). Dodajemo ih kako prolazimo kroz sablone.
       ============================================================ */

];
